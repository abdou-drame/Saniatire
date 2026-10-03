<?php

namespace App\Http\Controllers\Api\Platform;

use App\Domain\Platform\Mail\SubscriptionPaymentLinkMail;
use App\Domain\Platform\Models\PaymentTransaction;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Payments\DexPayException;
use App\Domain\Platform\Payments\SubscriptionCheckout;
use App\Domain\Platform\SubscriptionRenewal;
use App\Domain\Structure\Models\Structure;
use App\Http\Controllers\Api\Platform\Concerns\AuditsPlatformActions;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentTransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Paiement DexPay déclenché par l'administration plateforme (cas gérés à
 * la main : lien envoyé au client, négociation commerciale). Coexiste avec
 * la saisie manuelle des périodes (PlatformSubscriptionController) : seul
 * le webhook crée la période, une fois le paiement confirmé.
 */
class PlatformPaymentController extends Controller
{
    use AuditsPlatformActions;

    public function index(Structure $structure): AnonymousResourceCollection
    {
        $transactions = PaymentTransaction::query()
            ->with('plan')
            ->where('structure_id', $structure->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return PaymentTransactionResource::collection($transactions);
    }

    public function checkout(Request $request, Structure $structure, SubscriptionCheckout $checkout): JsonResponse
    {
        abort_if($structure->trashed(), 409, 'Cette structure est archivée : elle reste consultable mais ne peut plus être modifiée.');

        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'period' => ['required', Rule::in(PaymentTransaction::PERIODS)],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);
        // Montant toujours lu dans la grille, jamais saisi.
        $amount = SubscriptionRenewal::amountFor($plan, $data['period']);
        abort_if($amount === null, 422, "La formule {$plan->name} n'a pas de tarif pour cette périodicité (sur devis) : enregistrez la période à la main.");

        $admin = $request->user('platform');

        try {
            $transaction = $checkout->start($structure, $plan, $data['period'], $amount, "platform_admin:{$admin->id}");
        } catch (DexPayException $e) {
            abort(502, $e->getMessage());
        }

        $this->auditPlatformAction($request, $transaction, $structure->id, 'creation_session_paiement_dexpay', [
            'reference' => $transaction->reference,
            'formule' => $plan->code,
            'periode' => $transaction->period,
            'montant' => $transaction->amount,
        ]);

        return (new PaymentTransactionResource($transaction->load('plan')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Envoi du lien à l'adresse enregistrée de la structure (jamais une
     * adresse saisie). Synchrone : un échec SMTP renvoie une erreur claire
     * et n'est pas journalisé comme un envoi.
     */
    public function sendEmail(Request $request, Structure $structure, PaymentTransaction $transaction): JsonResponse
    {
        abort_if($structure->trashed(), 409, 'Cette structure est archivée : elle reste consultable mais ne peut plus être modifiée.');
        abort_if((int) $transaction->structure_id !== (int) $structure->id, 404);
        abort_if(blank($structure->email), 422, "Aucune adresse email n'est enregistrée pour cette structure : complétez sa fiche ou transmettez le lien autrement.");
        abort_if(
            $transaction->status !== PaymentTransaction::STATUS_PENDING || blank($transaction->payment_url),
            422,
            "Ce lien de paiement n'est plus utilisable (paiement déjà traité) : générez-en un nouveau.",
        );
        abort_if(
            SubscriptionPaymentLinkMail::expiresAt($transaction)->isPast(),
            422,
            'Ce lien de paiement a expiré : générez-en un nouveau avant de l\'envoyer.',
        );

        try {
            Mail::to($structure->email)->send(new SubscriptionPaymentLinkMail($structure, $transaction->load('plan')));
        } catch (Throwable $e) {
            Log::error('Envoi du lien de paiement DexPay par email impossible', [
                'structure_id' => $structure->id,
                'reference' => $transaction->reference,
                'erreur' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => "L'email n'a pas pu être envoyé (serveur d'envoi indisponible). Le lien n'a pas été transmis : réessayez plus tard ou copiez-le manuellement.",
            ], 503);
        }

        $this->auditPlatformAction($request, $transaction, $structure->id, 'envoi_lien_paiement_dexpay_email', [
            'reference' => $transaction->reference,
            'destinataire' => $structure->email,
            'montant' => $transaction->amount,
        ]);

        return response()->json(['data' => ['sent_to' => $structure->email]]);
    }
}
