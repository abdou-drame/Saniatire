<?php

namespace App\Http\Controllers\Api\Platform;

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
use Illuminate\Validation\Rule;

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
}
