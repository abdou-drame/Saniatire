<?php

namespace App\Http\Controllers\Api;

use App\Domain\Platform\Models\PaymentTransaction;
use App\Domain\Platform\Models\Subscription;
use App\Domain\Platform\Payments\DexPayException;
use App\Domain\Platform\Payments\SubscriptionCheckout;
use App\Domain\Platform\SubscriptionRenewal;
use App\Domain\Platform\SubscriptionState;
use App\Domain\Structure\Models\Structure;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentTransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Renouvellement en self-service par la structure elle-même. Aucun
 * paramètre : la structure est celle de l'utilisateur connecté, la formule
 * et la périodicité sont celles de sa dernière période (ce n'est pas un
 * changement d'offre). Autorisée même en lecture seule (exception dans
 * EnsureSubscriptionWritable) : c'est précisément le moyen d'en sortir.
 */
class SubscriptionPaymentController extends Controller
{
    /**
     * Écran « Mon abonnement », consultable à tout moment (pas seulement
     * en urgence) : période en cours, période déjà payée à venir, tarif et
     * dates du prochain renouvellement, derniers paiements. Mêmes rôles que
     * le paiement : ce sont des informations de facturation.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(SubscriptionState::ALERTED_ROLES), 403, "Seuls l'administrateur et la direction de la structure peuvent consulter l'abonnement.");

        $structureId = (int) $user->structure_id;
        $state = SubscriptionState::forStructure($structureId);
        $current = $state->subscription;

        $upcoming = Subscription::query()
            ->with('plan')
            ->where('structure_id', $structureId)
            ->whereIn('status', ['essai', 'active'])
            ->whereDate('starts_at', '>', now()->startOfDay())
            ->orderBy('starts_at')
            ->first();

        $offer = SubscriptionRenewal::selfServiceOffer($structureId);
        $renewal = null;
        if (! isset($offer['error'])) {
            [$startsAt, $endsAt] = SubscriptionRenewal::nextPeriodDates($structureId, $offer['period']);
            $renewal = [
                'plan_name' => $offer['plan']->name,
                'period' => $offer['period'],
                'amount' => $offer['amount'],
                'currency' => 'XOF',
                'starts_at' => $startsAt->toDateString(),
                'ends_at' => $endsAt->toDateString(),
            ];
        }

        $payments = PaymentTransaction::query()
            ->with('plan')
            ->where('structure_id', $structureId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $period = fn (?Subscription $s) => $s ? [
            'plan_name' => $s->plan?->name,
            'status' => $s->status,
            'billing_period' => SubscriptionRenewal::billingPeriodOf($s),
            'starts_at' => $s->starts_at->toDateString(),
            'ends_at' => $s->ends_at->toDateString(),
            'grace_ends_at' => SubscriptionState::graceEndsAt($s)->toDateString(),
        ] : null;

        return response()->json(['data' => [
            'state' => $state->state,
            'read_only' => $state->isReadOnly(),
            'current' => $period($current),
            'upcoming' => $period($upcoming),
            'renewal' => $renewal,
            'can_pay_online' => $renewal !== null,
            'unavailable_reason' => $offer['error'] ?? null,
            'payments' => PaymentTransactionResource::collection($payments)->resolve($request),
        ]]);
    }

    public function checkout(Request $request, SubscriptionCheckout $checkout): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(SubscriptionState::ALERTED_ROLES), 403, "Seuls l'administrateur et la direction de la structure peuvent régler l'abonnement.");

        $structure = Structure::findOrFail($user->structure_id);
        $offer = SubscriptionRenewal::selfServiceOffer($structure->id);
        abort_if(isset($offer['error']), 422, $offer['error'] ?? '');

        try {
            $transaction = $checkout->start($structure, $offer['plan'], $offer['period'], $offer['amount'], "structure_admin:{$user->id}");
        } catch (DexPayException $e) {
            abort(502, $e->getMessage());
        }

        // Visible dans le journal de la plateforme, qui suit la facturation.
        activity('administration_plateforme')
            ->causedBy($user)
            ->performedOn($transaction)
            ->withProperties([
                'action' => 'creation_session_paiement_dexpay',
                'origine' => 'self_service_structure',
                'declenche_par' => $transaction->initiated_by,
                'reference' => $transaction->reference,
                'formule' => $offer['plan']->code,
                'periode' => $transaction->period,
                'montant' => $transaction->amount,
            ])
            ->tap(function ($activity) use ($structure) {
                $activity->structure_id = $structure->id;
            })
            ->log("Session de paiement DexPay ouverte par la structure pour le renouvellement de son abonnement.");

        return (new PaymentTransactionResource($transaction->load('plan')))
            ->response()
            ->setStatusCode(201);
    }
}
