<?php

namespace App\Domain\Ai\Providers;

use App\Domain\Ai\Contracts\AiProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Appelle l'API Messages d'Anthropic directement via le facade Http plutôt
 * qu'un SDK dédié — aucune nouvelle dépendance composer requise, cohérent
 * avec le reste du projet (ex. export CSV en PHP natif). N'est résolue par
 * le conteneur (voir AppServiceProvider::register()) que si
 * config('services.anthropic.key') est renseignée ; sinon SimulatedAiProvider
 * prend le relais.
 */
class AnthropicAiProvider implements AiProvider
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    public function summarizeConsultation(array $context): string
    {
        $prompt = $this->buildPrompt($context);

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => self::API_VERSION,
        ])->timeout(30)->post(self::API_URL, [
            'model' => config('services.anthropic.model'),
            'max_tokens' => 512,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Échec de l'appel à l'API Anthropic : ".$response->status());
        }

        $text = $response->json('content.0.text');

        if (! is_string($text) || $text === '') {
            throw new RuntimeException("Réponse Anthropic vide ou inattendue.");
        }

        return $text;
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function buildPrompt(array $context): string
    {
        $motif = $context['reason'] ?? 'non renseigné';
        $examenClinique = $context['clinical_exam'] ?? 'non renseigné';
        $diagnostics = $context['diagnoses'] ?? [];
        $diagnosticsTexte = empty($diagnostics) ? 'aucun' : implode(', ', $diagnostics);

        return <<<PROMPT
Tu es un assistant médical. Rédige un résumé clinique concis (5 lignes maximum,
en français) de cette consultation, à partir des éléments déjà saisis par le
praticien. Ne propose jamais de diagnostic nouveau ni de traitement non déjà
mentionné — reformule et synthétise uniquement ce qui suit.

Motif de consultation : {$motif}
Examen clinique : {$examenClinique}
Diagnostics codifiés : {$diagnosticsTexte}
PROMPT;
    }
}
