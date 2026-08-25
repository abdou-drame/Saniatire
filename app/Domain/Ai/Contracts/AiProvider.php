<?php

namespace App\Domain\Ai\Contracts;

/**
 * Contrat unique implémenté par chaque fournisseur IA (Anthropic, simulé...).
 * Le reste de l'application ne connaît que ce contrat, résolu depuis le
 * conteneur (voir AppServiceProvider::register()) — jamais une implémentation
 * concrète directement. Pour brancher un nouveau fournisseur : créer une
 * classe qui l'implémente et l'y résoudre, sans toucher aux appelants.
 *
 * Exigence non négociable du projet : le texte retourné par
 * summarizeConsultation() est une PROPOSITION. Aucun appelant ne doit
 * l'enregistrer automatiquement dans le dossier patient — seule une action
 * explicite du praticien (l'endpoint standard de mise à jour de la
 * consultation, tracé par LogsActivity) peut la faire persister.
 */
interface AiProvider
{
    /**
     * @param  array<string,mixed>  $context  motif, examen clinique, diagnostics déjà saisis
     * @return string résumé proposé — jamais persisté automatiquement par l'appelant
     *
     * @throws \Throwable en cas d'échec (clé invalide, timeout, erreur API...)
     */
    public function summarizeConsultation(array $context): string;
}
