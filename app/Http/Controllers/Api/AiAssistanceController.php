<?php

namespace App\Http\Controllers\Api;

use App\Domain\Ai\Contracts\AiProvider;
use App\Domain\Ai\Services\AnomalyDetectionService;
use App\Domain\Consultation\Models\Consultation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Étape 9 §4 : ces deux endpoints ne PERSISTENT jamais rien dans le dossier
 * du patient. summary() renvoie une proposition de texte ; anomalies()
 * renvoie une liste calculée. Le seul moyen d'inscrire un de ces résultats
 * dans le dossier reste l'endpoint standard de mise à jour de la
 * consultation (ConsultationController::update), déjà tracé par
 * LogsActivity — geste explicite et attribuable au praticien.
 */
class AiAssistanceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:ai.consultation_summary', only: ['summary']),
            new Middleware('permission:ai.anomaly_detection', only: ['anomalies']),
        ];
    }

    public function summary(Consultation $consultation, AiProvider $aiProvider): JsonResponse
    {
        $context = [
            'reason' => $consultation->reason,
            'clinical_exam' => $consultation->clinical_exam,
            'diagnoses' => $consultation->diagnoses()->pluck('label_snapshot')->all(),
        ];

        try {
            $summary = $aiProvider->summarizeConsultation($context);
        } catch (\Throwable $e) {
            Log::warning('Échec de la génération du résumé IA', [
                'consultation_id' => $consultation->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => "Le service de résumé IA est momentanément indisponible. Aucune donnée n'a été affectée.",
            ], 503);
        }

        activity('ai')
            ->causedBy(Auth::user())
            ->performedOn($consultation)
            ->event('ia_suggestion_generee')
            ->withProperties(['type' => 'consultation_summary'])
            ->log("Proposition de résumé IA générée pour la consultation #{$consultation->id} (non enregistrée automatiquement).");

        return response()->json([
            'summary' => $summary,
            'persisted' => false,
            'notice' => "Cette proposition n'est pas enregistrée. Seule une validation explicite du praticien via la mise à jour de la consultation peut l'inscrire au dossier.",
        ]);
    }

    public function anomalies(Consultation $consultation, AnomalyDetectionService $service): JsonResponse
    {
        return response()->json([
            'consultation_id' => $consultation->id,
            'method' => 'comparaison_simple',
            'anomalies' => $service->detectForConsultation($consultation),
        ]);
    }
}
