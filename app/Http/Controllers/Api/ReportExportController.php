<?php

namespace App\Http\Controllers\Api;

use App\Domain\Dashboard\Services\FinancialDashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Étape 8 §5 : export CSV minimal (pas de PDF). Patron standard Laravel
 * (StreamedResponse + fputcsv), aucune nouvelle dépendance. Réutilise les
 * mêmes sources de données que les tableaux de bord plutôt que de
 * dupliquer une formule.
 */
class ReportExportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:dashboards.export', only: ['epidemiologie', 'chiffreAffaires', 'balanceAgee']),
        ];
    }

    public function epidemiologie(Request $request): StreamedResponse
    {
        $stats = app(IcdCodeController::class)->stats($request)->getData(true)['stats'];

        $header = empty($stats) ? ['aucune_donnee'] : array_keys($stats[0]);

        return $this->csv('epidemiologie.csv', $header, $stats);
    }

    public function chiffreAffaires(Request $request, FinancialDashboardService $service): StreamedResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->subMonth()->startOfDay();
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();

        $rows = [];

        foreach ($service->encaissementsParSite($from, $to) as $row) {
            $rows[] = ['dimension' => 'site', 'cle' => $row['site_id'], 'total' => $row['total']];
        }

        foreach ($service->encaissementsParModePaiement(null, $from, $to) as $row) {
            $rows[] = ['dimension' => 'mode_paiement', 'cle' => $row['mode_paiement'], 'total' => $row['total']];
        }

        return $this->csv('chiffre-affaires.csv', ['dimension', 'cle', 'total'], $rows);
    }

    public function balanceAgee(Request $request): StreamedResponse
    {
        $lignes = app(CreancesController::class)->balanceAgee($request)->getData(true)['lignes'];

        $header = empty($lignes) ? ['aucune_donnee'] : array_keys($lignes[0]);

        return $this->csv('balance-agee.csv', $header, $lignes);
    }

    /**
     * @param  array<int,string>  $header
     * @param  array<int,array<string,mixed>>  $rows
     */
    private function csv(string $filename, array $header, array $rows): StreamedResponse
    {
        return new StreamedResponse(function () use ($header, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $header);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ]);
    }
}
