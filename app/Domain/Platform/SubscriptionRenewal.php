<?php

namespace App\Domain\Platform;

use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\Subscription;
use Illuminate\Support\Carbon;

/**
 * Règles de renouvellement partagées par le paiement en ligne (plateforme
 * et self-service) et le webhook : montant officiel d'une formule, formule
 * et périodicité à reconduire, dates de la période suivante.
 */
class SubscriptionRenewal
{
    /** Au-delà, une période saisie à la main sans billing_period est considérée annuelle. */
    private const ANNUAL_MIN_DAYS = 300;

    /**
     * Tarif officiel de la grille (table plans), jamais une valeur saisie :
     * null pour une formule sur devis (Enterprise) ou inactive.
     */
    public static function amountFor(Plan $plan, string $period): ?int
    {
        if (! $plan->is_active) {
            return null;
        }

        $amount = $period === 'annual' ? $plan->annual_price_fcfa : $plan->monthly_price_fcfa;

        return $amount > 0 ? $amount : null;
    }

    /** Dernière période connue de la structure (la plus récente par date de début). */
    public static function lastPeriod(int $structureId): ?Subscription
    {
        return Subscription::query()
            ->with('plan')
            ->where('structure_id', $structureId)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Périodicité d'une période : celle enregistrée au paiement, sinon
     * déduite de sa durée pour les périodes saisies à la main (environ un
     * an => annuel, sinon mensuel).
     */
    public static function billingPeriodOf(Subscription $subscription): string
    {
        if (in_array($subscription->billing_period, ['monthly', 'annual'], true)) {
            return $subscription->billing_period;
        }

        return $subscription->starts_at->diffInDays($subscription->ends_at) >= self::ANNUAL_MIN_DAYS ? 'annual' : 'monthly';
    }

    /**
     * Offre de renouvellement en self-service : même formule et même
     * périodicité que la dernière période. Renvoie ['error' => message]
     * quand le paiement en ligne n'est pas possible (aucune période,
     * période suspendue/résiliée par la plateforme, formule sur devis).
     *
     * @return array{plan: Plan, period: string, amount: int}|array{error: string}
     */
    public static function selfServiceOffer(int $structureId): array
    {
        $last = self::lastPeriod($structureId);

        if (! $last || ! $last->plan) {
            return ['error' => "Aucun abonnement n'est encore enregistré pour votre structure : contactez Saliha Health pour mettre en place votre formule."];
        }

        // Une suspension ou une résiliation est une décision de la
        // plateforme : un paiement en ligne ne doit pas la contourner.
        $current = SubscriptionState::forStructure($structureId)->subscription;
        if ($current && in_array($current->status, ['suspendue', 'resiliee'], true)) {
            return ['error' => "L'abonnement de votre structure est {$current->status} : contactez Saliha Health pour le rétablir."];
        }

        $period = self::billingPeriodOf($last);
        $amount = self::amountFor($last->plan, $period);

        if ($amount === null) {
            return ['error' => "Votre formule ({$last->plan->name}) n'a pas de tarif en ligne : contactez Saliha Health pour son renouvellement."];
        }

        return ['plan' => $last->plan, 'period' => $period, 'amount' => $amount];
    }

    /**
     * Dates de la nouvelle période (bornes incluses, comme les périodes
     * manuelles) : elle commence aujourd'hui, ou le lendemain de la fin de
     * la période en cours si du temps payé reste à courir.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function nextPeriodDates(int $structureId, string $period, ?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();

        $latestEnd = Subscription::query()
            ->where('structure_id', $structureId)
            ->whereIn('status', ['essai', 'active'])
            ->max('ends_at');

        $startsAt = $today->copy();
        if ($latestEnd && Carbon::parse($latestEnd)->startOfDay()->gte($today)) {
            $startsAt = Carbon::parse($latestEnd)->startOfDay()->addDay();
        }

        $endsAt = $period === 'annual'
            ? $startsAt->copy()->addYearNoOverflow()->subDay()
            : $startsAt->copy()->addMonthNoOverflow()->subDay();

        return [$startsAt, $endsAt];
    }
}
