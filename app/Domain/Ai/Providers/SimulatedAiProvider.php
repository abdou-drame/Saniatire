<?php

namespace App\Domain\Ai\Providers;

use App\Domain\Ai\Contracts\AiProvider;

/**
 * Comportement dégradé : aucune clé Anthropic configurée => aucun appel
 * réseau, aucune erreur bloquante, un résumé simulé clairement identifié
 * comme tel est retourné. Même patron que les canaux de notification non
 * encore branchés (SmsChannel, WhatsAppChannel, PushChannel).
 */
class SimulatedAiProvider implements AiProvider
{
    public function summarizeConsultation(array $context): string
    {
        $motif = $context['reason'] ?? 'non renseigné';

        return "[Résumé simulé — clé ANTHROPIC_API_KEY non configurée] ".
            "Consultation pour : {$motif}. Configurez ANTHROPIC_API_KEY pour obtenir ".
            'une véritable proposition de résumé générée par IA.';
    }
}
