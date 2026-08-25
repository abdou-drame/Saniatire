<?php

namespace App\Http\Controllers\Api;

use App\Domain\Dashboard\Services\MedicalDashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;

class DashboardMedicalController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:dashboards.medical', only: ['index']),
        ];
    }

    /**
     * Étape 8 §1 : indicateurs médicaux consolidés en une seule réponse.
     * Les endpoints déjà posés aux étapes 2/3 (épidémiologie, occupation
     * des lits, temps d'attente) sont appelés directement — même
     * $request, donc mêmes filtres from/to/site_id — plutôt que dupliqués.
     * icd.export/hospitalisation.view/queue.view ne sont pas re-vérifiés
     * ici : l'accès à ce tableau de bord est gouverné uniquement par
     * dashboards.medical (appel direct au contrôleur, pas de routing donc
     * pas de pipeline de middleware).
     */
    public function index(Request $request, MedicalDashboardService $service): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'site_id' => ['nullable', 'integer'],
        ]);

        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->subMonth()->startOfDay();
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();
        $siteId = $data['site_id'] ?? null;

        $epidemiologie = app(IcdCodeController::class)->stats($request)->getData(true);
        $occupationLits = app(WardController::class)->occupancyStats($request)->getData(true);
        $tempsAttente = app(QueueEntryController::class)->stats($request)->getData(true);

        return response()->json([
            'meta' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'site_id' => $siteId],
            'consultations' => $service->consultationsSummary($siteId, $from, $to),
            'epidemiologie' => $epidemiologie,
            'occupation_lits' => $occupationLits['data'] ?? $occupationLits,
            'temps_attente' => $tempsAttente,
            'actes_par_specialite' => [
                'site_filtre_applique' => false,
                'data' => $service->actesParSpecialite($from, $to),
            ],
        ]);
    }
}
