<?php

namespace App\Domain\Platform\Payments;

use App\Domain\Platform\Models\PaymentTransaction;
use App\Domain\Platform\Models\Subscription;
use App\Domain\Platform\SubscriptionRenewal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Traitement d'un webhook DexPay dont la signature a DÉJÀ été vérifiée
 * (DexPayWebhookController). Idempotent : DexPay renvoie le même
 * événement jusqu'à 3 fois, et un événement rejoué ne doit jamais créer
 * une seconde période d'abonnement. Garanties :
 * - verrou sur la ligne payment_transactions pendant tout le traitement
 *   (deux livraisons simultanées sont sérialisées) ;
 * - statut `complete` déjà atteint => rien n'est recréé ;
 * - contraintes uniques transaction_id / subscription_id en dernier
 *   recours.
 *
 * Deux formes de payload existent dans la documentation DexPay (champs à
 * plat, ou sous `data`) : les deux sont acceptées.
 */
class DexPayWebhookProcessor
{
    /** @param  array<string, mixed>  $payload */
    public function handle(array $payload): void
    {
        $event = is_string($payload['event'] ?? null) ? $payload['event'] : null;
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $reference = is_string($data['reference'] ?? null) ? $data['reference'] : null;

        if (! in_array($event, ['checkout.initiated', 'checkout.completed', 'checkout.failed', 'checkout.cancelled'], true)) {
            Log::info('Webhook DexPay : événement non traité.', ['event' => $event, 'reference' => $reference]);

            return;
        }

        $transactionId = PaymentTransaction::query()
            ->where('provider', 'dexpay')
            ->where('reference', $reference)
            ->value('id');

        if (! $transactionId) {
            Log::warning('Webhook DexPay : référence inconnue.', ['event' => $event, 'reference' => $reference]);

            return;
        }

        DB::transaction(function () use ($transactionId, $event, $data, $payload) {
            $transaction = PaymentTransaction::query()->whereKey($transactionId)->lockForUpdate()->firstOrFail();

            match ($event) {
                'checkout.completed' => $this->completed($transaction, $data, $payload),
                'checkout.failed' => $this->closed($transaction, $data, $payload, PaymentTransaction::STATUS_FAILED),
                'checkout.cancelled' => $this->closed($transaction, $data, $payload, PaymentTransaction::STATUS_CANCELLED),
                'checkout.initiated' => $this->initiated($transaction, $data, $payload),
            };
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $payload
     */
    private function completed(PaymentTransaction $transaction, array $data, array $payload): void
    {
        if ($transaction->status === PaymentTransaction::STATUS_COMPLETED) {
            return; // Rejeu d'un paiement déjà traité.
        }

        // Défense en profondeur : même signé, un paiement dont le montant
        // ou la devise diffère de la session créée n'ouvre aucune période.
        if ((int) ($data['amount'] ?? -1) !== $transaction->amount || ($data['currency'] ?? null) !== $transaction->currency) {
            Log::warning('Webhook DexPay : montant ou devise incohérent, aucune période créée.', [
                'reference' => $transaction->reference,
                'attendu' => [$transaction->amount, $transaction->currency],
                'recu' => [$data['amount'] ?? null, $data['currency'] ?? null],
            ]);
            $transaction->update(['raw_payload' => $payload]);

            return;
        }

        [$startsAt, $endsAt] = SubscriptionRenewal::nextPeriodDates($transaction->structure_id, $transaction->period);

        $subscription = Subscription::create([
            'structure_id' => $transaction->structure_id,
            'plan_id' => $transaction->plan_id,
            'starts_at' => $startsAt->toDateString(),
            'ends_at' => $endsAt->toDateString(),
            'status' => 'active',
            'billing_period' => $transaction->period,
            'notes' => "Paiement DexPay automatique — référence {$transaction->reference}.",
            // Aucun administrateur n'agit : created_by reste vide, l'origine
            // est portée par la note, la transaction et le journal.
            'created_by' => null,
        ]);

        $transaction->update([
            'status' => PaymentTransaction::STATUS_COMPLETED,
            'transaction_id' => $data['transaction_id'] ?? $transaction->transaction_id,
            'checkout_session_id' => $data['checkout_session_id'] ?? $transaction->checkout_session_id,
            'raw_payload' => $payload,
            'subscription_id' => $subscription->id,
        ]);

        $this->audit($transaction, $subscription, 'renouvellement_paiement_dexpay',
            "Paiement DexPay confirmé : période d'abonnement créée automatiquement, sans action manuelle d'un administrateur.", [
                'starts_at' => $subscription->starts_at->toDateString(),
                'ends_at' => $subscription->ends_at->toDateString(),
                'operateur' => $data['operator'] ?? null,
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $payload
     */
    private function closed(PaymentTransaction $transaction, array $data, array $payload, string $status): void
    {
        // Un échec ou une annulation tardive ne défait jamais un paiement
        // déjà confirmé, et un rejeu ne journalise pas deux fois.
        if ($transaction->status !== PaymentTransaction::STATUS_PENDING) {
            return;
        }

        $transaction->update([
            'status' => $status,
            'transaction_id' => $transaction->transaction_id ?? ($data['transaction_id'] ?? null),
            'checkout_session_id' => $transaction->checkout_session_id ?? ($data['checkout_session_id'] ?? null),
            'raw_payload' => $payload,
        ]);

        $failed = $status === PaymentTransaction::STATUS_FAILED;
        $this->audit($transaction, $transaction, $failed ? 'paiement_dexpay_echoue' : 'paiement_dexpay_annule',
            $failed ? 'Paiement DexPay échoué : aucune période créée.' : 'Paiement DexPay annulé par le client : aucune période créée.', [
                'motif' => $data['failure_reason'] ?? null,
            ]);
    }

    /**
     * Simple trace : la session est ouverte côté DexPay, aucune action métier.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $payload
     */
    private function initiated(PaymentTransaction $transaction, array $data, array $payload): void
    {
        if ($transaction->status !== PaymentTransaction::STATUS_PENDING) {
            return;
        }

        $transaction->update([
            'transaction_id' => $transaction->transaction_id ?? ($data['transaction_id'] ?? null),
            'checkout_session_id' => $transaction->checkout_session_id ?? ($data['checkout_session_id'] ?? null),
            'raw_payload' => $payload,
        ]);
    }

    /** Même log_name que les actions plateforme, sans auteur : c'est le prestataire qui notifie. */
    private function audit(PaymentTransaction $transaction, Model $subject, string $action, string $description, array $properties): void
    {
        activity('administration_plateforme')
            ->performedOn($subject)
            ->withProperties([
                ...$properties,
                'action' => $action,
                'automatique' => true,
                'origine' => 'webhook_dexpay',
                'reference' => $transaction->reference,
                'montant' => $transaction->amount,
                'periode' => $transaction->period,
                'declenche_par' => $transaction->initiated_by,
                'hors_isolation' => true,
            ])
            ->tap(function ($activity) use ($transaction) {
                $activity->structure_id = $transaction->structure_id;
            })
            ->log($description);
    }
}
