<?php

namespace App\Http\Controllers\Api;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Dashboard\Services\FinancialDashboardService;
use App\Domain\Hospitalisation\Models\Ward;
use App\Domain\Shared\Tenancy\TenantScope;
use App\Domain\Structure\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardDirectionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:dashboards.direction', only: ['index']),
        ];
    }

    /**
     * Étape 8 §3 : vue consolidée multi-sites d'UNE structure (pas de
     * notion de "Groupe" multi-structures dans ce projet — voir
     * README). Aucun bypass de TenantScope : Site::all() et les requêtes
     * ci-dessous sont déjà bornées à la structure de l'utilisateur
     * connecté, donc l'isolation est gratuite. Indicateur explicitement
     * cité comme coûteux par le prompt -> mis en cache, clé incluant
     * systématiquement structure_id (voir README, point de vigilance
     * fuite inter-structure).
     */
    public function index(Request $request, FinancialDashboardService $service): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->subMonth()->startOfDay();
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();

        $structureId = TenantScope::currentStructureId();
        $cacheKey = sprintf('dashboard.direction.%s.%s.%s', $structureId ?? 'none', $from->toDateString(), $to->toDateString());

        $payload = Cache::remember($cacheKey, now()->addMinutes(10), fn () => $this->build($service, $from, $to));

        return response()->json([
            'meta' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            ...$payload,
        ]);
    }

    private function build(FinancialDashboardService $service, Carbon $from, Carbon $to): array
    {
        $sites = Site::query()->get();

        $sitesIndicateurs = $sites->map(function (Site $site) use ($service, $from, $to) {
            return [
                'site_id' => $site->id,
                'site_name' => $site->name,
                'ca' => $service->totalEncaissements($site->id, $from, $to),
                'nombre_patients' => $this->patientsDistincts($site->id, $from, $to),
                'taux_occupation' => $this->tauxOccupation($site->id),
            ];
        })->sortByDesc('ca')->values();

        $comparaisonSites = $sitesIndicateurs
            ->map(fn (array $row, int $index) => [...$row, 'rang' => $index + 1])
            ->all();

        return [
            'consolide' => [
                'ca_total' => $service->totalEncaissements(null, $from, $to),
                'nombre_patients' => $this->patientsDistincts(null, $from, $to),
                'taux_occupation_moyen' => $this->tauxOccupation(null),
            ],
            'comparaison_sites' => $comparaisonSites,
        ];
    }

    private function patientsDistincts(?int $siteId, Carbon $from, Carbon $to): int
    {
        return Consultation::query()
            ->where('status', 'terminee')
            ->whereBetween('created_at', [$from, $to])
            ->when($siteId, fn ($q, $id) => $q->where('site_id', $id))
            ->distinct('patient_id')
            ->count('patient_id');
    }

    /**
     * Site donné : taux global (lits occupés / lits totaux de ses wards).
     * Structure entière (siteId=null) : moyenne simple des taux par ward,
     * lecture littérale du plan ("moyenne des taux ... toutes wards de la
     * structure") — non pondérée par la taille de chaque ward.
     */
    private function tauxOccupation(?int $siteId): ?float
    {
        $wards = Ward::query()
            ->when($siteId, fn ($q, $id) => $q->where('site_id', $id))
            ->withCount([
                'beds',
                'beds as occupied_beds_count' => fn ($q) => $q->where('status', 'occupe'),
            ])
            ->get();

        if ($wards->isEmpty()) {
            return null;
        }

        if ($siteId) {
            $totalBeds = $wards->sum('beds_count');

            return $totalBeds > 0 ? round($wards->sum('occupied_beds_count') / $totalBeds * 100, 1) : 0.0;
        }

        $rates = $wards->map(fn (Ward $w) => $w->beds_count > 0 ? $w->occupied_beds_count / $w->beds_count * 100 : 0.0);

        return round($rates->avg(), 1);
    }
}
