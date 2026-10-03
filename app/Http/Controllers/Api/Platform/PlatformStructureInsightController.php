<?php

namespace App\Http\Controllers\Api\Platform;

use App\Domain\Shared\Tenancy\TenantScope;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformStructureUserResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Livraison D : supervision des structures par l'administrateur de
 * plateforme (personnel, activité, statistiques globales).
 *
 * Exception délibérée à l'isolation multi-structures, au même titre que le
 * référencement inter-structures (PatientReferralController, borné par
 * ReferralVisibilityScope) : ces routes lisent des données de plusieurs
 * structure_id, mais uniquement sous forme d'agrégats. Aucun détail
 * patient n'est jamais exposé (ni nom, ni patient_number, ni contact), et
 * aucun contenu du journal d'audit (description, properties, causer —
 * properties peut contenir des données patient) : des comptages
 * seulement. Seule la liste du personnel (users()) renvoie des lignes
 * individuelles, et uniquement des comptes du personnel, jamais de
 * patients.
 *
 * TenantScope : sous le guard platform, currentStructureId() est null, le
 * scope global ne filtre donc RIEN. Chaque requête ci-dessous filtre
 * explicitement par structure_id (ou regroupe explicitement par
 * structure_id) via le query builder DB::table(), qui n'applique aucun
 * scope global — ou retire explicitement TenantScope côté Eloquent. Jamais
 * de dépendance au scope implicite.
 *
 * Lectures seules : non journalisées (pas d'AuditsPlatformActions ici), la
 * trace 'administration_plateforme' est réservée aux actions qui modifient
 * quelque chose.
 *
 * Dates : les colonnes timestamp sont stockées dans le fuseau de
 * l'application (config app.timezone), now() aussi — toutes les bornes et
 * tous les regroupements par jour/mois se font donc dans ce même fuseau.
 * Regroupements volontairement portables SQLite (tests) / PostgreSQL
 * (production) : DATE(created_at) existe sur les deux moteurs, et les mois
 * sont comptés par une requête groupée par intervalle (pas de fonction de
 * formatage de date propre à un moteur).
 */
class PlatformStructureInsightController extends Controller
{
    private const STATS_MONTHS = 6;

    /**
     * Personnel (modèle User) de la structure uniquement, comptes supprimés
     * (soft delete) exclus par le scope SoftDeletes du modèle. Rôles chargés
     * en une requête (with('roles')) ; teams Spatie désactivé
     * (config/permission.php), pas de contexte d'équipe à positionner.
     */
    public function users(Structure $structure): JsonResponse
    {
        $users = User::withoutGlobalScope(TenantScope::class)
            ->where('structure_id', $structure->id)
            ->with('roles')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25);

        return PlatformStructureUserResource::collection($users)->response();
    }

    /**
     * Activité de la structure sur les N derniers jours (aujourd'hui
     * inclus). actions_total / actions_per_day comptent les lignes
     * d'activity_log de la structure — jamais leur contenu.
     */
    public function activity(Request $request, Structure $structure): JsonResponse
    {
        $data = $request->validate([
            'days' => ['sometimes', 'integer', 'min:7', 'max:90'],
        ]);
        $days = (int) ($data['days'] ?? 30);

        $today = CarbonImmutable::now()->startOfDay();
        $from = $today->subDays($days - 1);
        $end = $today->addDay(); // borne exclusive : demain 00:00

        $staff = fn () => DB::table('users')
            ->where('structure_id', $structure->id)
            ->whereNull('deleted_at');

        $lastLogin = $staff()->max('last_login_at');

        $perDay = DB::table('activity_log')
            ->where('structure_id', $structure->id)
            ->where('created_at', '>=', $from->toDateTimeString())
            ->where('created_at', '<', $end->toDateTimeString())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->groupByRaw('DATE(created_at)')
            ->pluck('aggregate', 'day')
            ->mapWithKeys(fn ($count, $day) => [substr((string) $day, 0, 10) => (int) $count]);

        $actionsPerDay = [];
        for ($date = $from; $date->lt($end); $date = $date->addDay()) {
            $key = $date->toDateString();
            $actionsPerDay[] = ['date' => $key, 'count' => (int) ($perDay[$key] ?? 0)];
        }

        return response()->json(['data' => [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $today->toDateString(),
                'days' => $days,
            ],
            'last_login_at' => $lastLogin ? Carbon::parse($lastLogin)->toISOString() : null,
            'users_total' => $staff()->count(),
            'users_active' => $staff()->where('is_active', true)->count(),
            'users_logged_in_period' => $staff()
                ->where('last_login_at', '>=', $from->toDateTimeString())
                ->where('last_login_at', '<', $end->toDateTimeString())
                ->count(),
            'actions_total' => array_sum(array_column($actionsPerDay, 'count')),
            'actions_per_day' => $actionsPerDay,
        ]]);
    }

    /**
     * Agrégats globaux : une requête groupée par structure_id et par
     * métrique (et par mois pour les consultations), jamais une requête par
     * structure.
     *
     * Patients : rattachés par patients.structure_id (structure d'origine
     * du dossier). Les accès partagés via patient_structure_access ne sont
     * volontairement pas comptés, pour qu'un patient ne soit compté qu'une
     * fois.
     *
     * Consultations : la table n'a pas de colonne de date métier (seulement
     * closed_at, nulle tant que la consultation est en cours) — created_at
     * sert de date de consultation. Consultations supprimées (soft delete)
     * exclues.
     */
    public function stats(): JsonResponse
    {
        $currentMonth = CarbonImmutable::now()->startOfMonth();
        $months = collect(range(self::STATS_MONTHS - 1, 0))
            ->map(fn (int $offset) => $currentMonth->subMonths($offset));
        $monthKeys = $months->map(fn (CarbonImmutable $month) => $month->format('Y-m'))->all();

        $structures = Structure::withTrashed()
            ->orderBy('legal_name')
            ->get(['id', 'legal_name', 'is_active', 'deleted_at']);

        $activeSince = CarbonImmutable::now()->subDays(30)->toDateTimeString();

        $usersActive30d = DB::table('users')
            ->whereNull('deleted_at')
            ->where('last_login_at', '>=', $activeSince)
            ->groupBy('structure_id')
            ->selectRaw('structure_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'structure_id');

        $patients = DB::table('patients')
            ->whereNull('deleted_at')
            ->groupBy('structure_id')
            ->selectRaw('structure_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'structure_id');

        // Une requête groupée par structure_id pour chaque mois.
        $consultationsByMonth = [];
        foreach ($months as $month) {
            $consultationsByMonth[$month->format('Y-m')] = DB::table('consultations')
                ->whereNull('deleted_at')
                ->where('created_at', '>=', $month->toDateTimeString())
                ->where('created_at', '<', $month->addMonth()->toDateTimeString())
                ->groupBy('structure_id')
                ->selectRaw('structure_id, COUNT(*) as aggregate')
                ->pluck('aggregate', 'structure_id');
        }

        $rows = $structures->map(fn (Structure $structure) => [
            'id' => $structure->id,
            'legal_name' => $structure->legal_name,
            'is_active' => (bool) $structure->is_active,
            'is_archived' => $structure->trashed(),
            'users_active_30d' => (int) ($usersActive30d[$structure->id] ?? 0),
            'patients' => (int) ($patients[$structure->id] ?? 0),
            'consultations_per_month' => array_map(fn (string $key) => [
                'month' => $key,
                'count' => (int) ($consultationsByMonth[$key][$structure->id] ?? 0),
            ], $monthKeys),
        ])->values()->all();

        $nonArchived = $structures->reject(fn (Structure $structure) => $structure->trashed());

        return response()->json(['data' => [
            'months' => $monthKeys,
            'totals' => [
                'structures' => [
                    'total' => $structures->count(),
                    'active' => $nonArchived->where('is_active', true)->count(),
                    'inactive' => $nonArchived->where('is_active', false)->count(),
                    'archived' => $structures->count() - $nonArchived->count(),
                ],
                'users_active_30d' => (int) $usersActive30d->sum(),
                'patients' => (int) $patients->sum(),
                'consultations_this_month' => (int) $consultationsByMonth[$currentMonth->format('Y-m')]->sum(),
            ],
            'structures' => $rows,
        ]]);
    }
}
