<?php

namespace App\Domain\Platform\Payments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Seul point de contact avec l'API DexPay (docs.dexpay.africa). Les
 * contrôleurs ne font jamais d'appel HTTP eux-mêmes.
 *
 * - Création de session : POST {base_url}/checkout-sessions, en-tête
 *   x-api-key = clé publique.
 * - Webhooks : en-tête X-Webhook-Signature = HMAC-SHA256 hexadécimal du
 *   corps brut, clé = clé secrète de l'environnement qui a créé la session.
 */
class DexPayService
{
    public function isConfigured(): bool
    {
        return filled(config('services.dexpay.public_key')) && filled(config('services.dexpay.secret_key'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{payment_url: string, response: array<string, mixed>}
     *
     * @throws DexPayException
     */
    public function createCheckoutSession(array $payload): array
    {
        if (! $this->isConfigured()) {
            throw new DexPayException('Le paiement en ligne n\'est pas configuré (clés DexPay absentes).');
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('services.dexpay.base_url'), '/'))
                ->withHeaders(['x-api-key' => (string) config('services.dexpay.public_key')])
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->post('/checkout-sessions', $payload);
        } catch (ConnectionException $e) {
            throw new DexPayException('DexPay est injoignable pour le moment.', previous: $e);
        }

        $body = $response->json() ?? [];

        if (! $response->successful()) {
            throw new DexPayException(
                'DexPay a refusé la création de la session de paiement (HTTP '.$response->status().').',
                $body,
            );
        }

        $data = is_array($body['data'] ?? null) ? $body['data'] : $body;
        $paymentUrl = $data['payment_url'] ?? $data['sandbox_payment_url'] ?? null;

        if (! is_string($paymentUrl) || $paymentUrl === '') {
            throw new DexPayException('Réponse DexPay inattendue : aucun lien de paiement.', $body);
        }

        return ['payment_url' => $paymentUrl, 'response' => $body];
    }

    /**
     * Vérification de la signature d'un webhook. $rawBody doit être le
     * corps EXACT reçu ($request->getContent()) : un JSON ré-encodé (ordre
     * des clés, échappements, nombres) ne donnerait pas la même empreinte.
     * hash_equals() : comparaison à temps constant, pas d'attaque par
     * mesure du temps de réponse.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        $secret = (string) config('services.dexpay.secret_key');

        if ($secret === '' || ! is_string($signature) || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, strtolower(trim($signature)));
    }
}
