<?php

namespace App\Domain\Platform\Payments;

use App\Domain\Platform\Models\PaymentTransaction;
use App\Domain\Platform\Models\Plan;
use App\Domain\Structure\Models\Structure;
use Illuminate\Support\Str;

/**
 * Création d'une session de paiement d'abonnement, commune à la route
 * plateforme et au self-service de la structure : seule l'origine
 * (initiated_by) diffère. La transaction est enregistrée en `en_attente`
 * AVANT l'appel à DexPay, puis complétée (lien) ou passée en `echoue`
 * selon la réponse.
 */
class SubscriptionCheckout
{
    public function __construct(private readonly DexPayService $dexPay) {}

    /**
     * @param  string  $initiatedBy  "platform_admin:{id}" ou "structure_admin:{id}"
     *
     * @throws DexPayException
     */
    public function start(Structure $structure, Plan $plan, string $period, int $amount, string $initiatedBy): PaymentTransaction
    {
        $transaction = PaymentTransaction::create([
            'structure_id' => $structure->id,
            'plan_id' => $plan->id,
            'period' => $period,
            'reference' => 'SUB-'.$structure->id.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
            'amount' => $amount,
            'currency' => 'XOF',
            'status' => PaymentTransaction::STATUS_PENDING,
            'provider' => 'dexpay',
            'initiated_by' => $initiatedBy,
        ]);

        $periodLabel = $period === 'annual' ? 'annuel' : 'mensuel';
        $returnUrl = rtrim((string) (config('services.dexpay.return_url') ?: config('app.url')), '/');

        try {
            $session = $this->dexPay->createCheckoutSession([
                'reference' => $transaction->reference,
                'item_name' => "Abonnement Saliha Health — {$plan->name} ({$periodLabel}) — {$structure->legal_name}",
                'amount' => $amount,
                'currency' => 'XOF',
                'success_url' => $returnUrl.'/mon-abonnement?paiement=succes',
                'failure_url' => $returnUrl.'/mon-abonnement?paiement=echec',
                'webhook_url' => config('services.dexpay.webhook_url') ?: url('/api/webhooks/dexpay'),
                // Un seul paiement par session : un renouvellement = une période.
                'is_one_shot_payment' => true,
                // Informatif uniquement : le webhook s'appuie sur NOTRE
                // ligne payment_transactions (retrouvée par la référence),
                // jamais sur ces valeurs renvoyées par le prestataire.
                'metadata' => [
                    'structure_id' => $structure->id,
                    'plan_id' => $plan->id,
                    'period' => $period,
                    'initiated_by' => $initiatedBy,
                ],
            ]);
        } catch (DexPayException $e) {
            $transaction->update([
                'status' => PaymentTransaction::STATUS_FAILED,
                'raw_payload' => ['erreur_creation' => $e->getMessage(), 'reponse' => $e->response],
            ]);

            throw $e;
        }

        $data = is_array($session['response']['data'] ?? null) ? $session['response']['data'] : [];

        $transaction->update([
            'payment_url' => $session['payment_url'],
            'checkout_session_id' => $data['checkout_session_id'] ?? $data['id'] ?? $data['_id'] ?? null,
        ]);

        return $transaction;
    }
}
