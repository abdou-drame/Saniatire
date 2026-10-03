<?php

namespace App\Http\Controllers\Api;

use App\Domain\Platform\Payments\DexPayService;
use App\Domain\Platform\Payments\DexPayWebhookProcessor;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook DexPay : route publique (aucun guard), authentifiée uniquement
 * par la signature HMAC du corps brut. Le traitement est synchrone et
 * court (quelques écritures) ; une exception renvoie un 500 et DexPay
 * retente (1 s, 2 s, 5 s), ce qui est voulu puisque le traitement est
 * idempotent.
 */
class DexPayWebhookController extends Controller
{
    public function __invoke(Request $request, DexPayService $dexPay, DexPayWebhookProcessor $processor): JsonResponse
    {
        // Corps brut, jamais $request->all() ni un JSON ré-encodé.
        $rawBody = $request->getContent();

        if (! $dexPay->verifyWebhookSignature($rawBody, $request->header('X-Webhook-Signature'))) {
            // Journal applicatif plutôt que activity_log : un appelant non
            // authentifié ne doit pas pouvoir remplir le journal
            // inaltérable de la plateforme.
            Log::warning('Webhook DexPay rejeté : signature absente ou invalide.', [
                'ip' => $request->ip(),
                'signature_presente' => $request->hasHeader('X-Webhook-Signature'),
                'taille_corps' => strlen($rawBody),
            ]);

            return response()->json(['message' => 'Signature invalide.'], 401);
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            Log::warning('Webhook DexPay : corps signé mais JSON illisible.');

            return response()->json(['message' => 'Corps JSON invalide.'], 400);
        }

        $processor->handle($payload);

        return response()->json(['received' => true]);
    }
}
