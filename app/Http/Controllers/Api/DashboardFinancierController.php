<?php

namespace App\Http\Controllers\Api;

use App\Domain\Dashboard\Services\FinancialDashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;

class DashboardFinancierController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:dashboards.financier', only: ['index']),
        ];
    }

    /**
     * Étape 8 §2. "Chiffre d'affaires" = encaissements (Payment.montant),
     * jamais le montant facturé — voir
     * app/Domain/Dashboard/README.md. balance_agee réutilise
     * CreancesController::balanceAgee() directement (formule déjà testée
     * à l'étape 5b) ; facturation.creances n'est pas re-vérifié ici, même
     * rationale que DashboardMedicalController pour icd.export.
     */
    public function index(Request $request, FinancialDashboardService $service): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'site_id' => ['nullable', 'integer'],
        ]);

        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->subMonth()->startOfDay();
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();
        $siteId = $data['site_id'] ?? null;

        $balanceAgee = app(CreancesController::class)->balanceAgee($request)->getData(true);

        return response()->json([
            'meta' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'site_id' => $siteId],
            'encaissements_total' => $service->totalEncaissements($siteId, $from, $to),
            'encaissements_par_site' => $service->encaissementsParSite($from, $to),
            'encaissements_par_mode_paiement' => $service->encaissementsParModePaiement($siteId, $from, $to),
            'recettes_facturees_par_prestation' => $service->recettesFactureesParPrestation($from, $to),
            'balance_agee' => $balanceAgee,
            'taux_recouvrement' => $service->tauxRecouvrement($siteId, $from, $to),
            'repartition_assureur_patient' => $service->repartitionAssureurPatient($siteId, $from, $to),
        ]);
    }
}
