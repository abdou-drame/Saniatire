<?php

namespace App\Http\Controllers\Api;

use App\Domain\Consultation\Models\ConsultationDiagnosis;
use App\Domain\Icd\Models\IcdCode;
use App\Domain\Shared\Tenancy\TenantScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\IcdCodeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class IcdCodeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:icd.view', only: ['index', 'show', 'children', 'equivalents']),
            new Middleware('permission:icd.export', only: ['stats']),
        ];
    }

    /**
     * Search by code/label keyword (partial, case-insensitive), optionally
     * scoped to a referentiel version and/or a parent (chapter/group).
     */
    public function index(Request $request): JsonResponse
    {
        $codes = IcdCode::query()
            ->when($request->string('version')->isNotEmpty(), fn ($q) => $q->where('version', $request->string('version')))
            ->when($request->has('parent_id'), fn ($q) => $q->where('parent_id', $request->integer('parent_id') ?: null))
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request) {
                // LOWER(...) LIKE ? is used instead of Postgres-only ILIKE
                // so this stays portable to the SQLite connection the test
                // suite runs against.
                $term = '%'.mb_strtolower($request->string('search')->toString()).'%';
                $q->where(fn ($q) => $q->whereRaw('LOWER(code) LIKE ?', [$term])->orWhereRaw('LOWER(label) LIKE ?', [$term]));
            })
            ->orderBy('code')
            ->paginate();

        return IcdCodeResource::collection($codes)->response();
    }

    public function show(IcdCode $icdCode): IcdCodeResource
    {
        return new IcdCodeResource($icdCode);
    }

    /**
     * Hierarchical navigation: direct children of a chapter/group.
     */
    public function children(IcdCode $icdCode): JsonResponse
    {
        $children = IcdCode::query()
            ->where('parent_id', $icdCode->id)
            ->orderBy('code')
            ->get();

        return IcdCodeResource::collection($children)->response();
    }

    /**
     * CIM-10 <-> CIM-11 equivalents via icd_code_mappings, looked up in
     * either direction from this code.
     */
    public function equivalents(IcdCode $icdCode): JsonResponse
    {
        return IcdCodeResource::collection($icdCode->equivalents())->response();
    }

    /**
     * Diagnosis counts by code or by chapter over a period — base dataset
     * for the epidemiological dashboards (étape 8 §1). Reads consultation
     * diagnoses' frozen snapshots, not the live icd_codes table, so counts
     * reflect what was actually coded at the time.
     *
     * site_id/sex/age_min/age_max (étape 8) are optional and join out to
     * consultations/patients only when at least one is supplied — no
     * filter given keeps the exact original query (and cache key) so
     * pre-existing callers/tests see no behaviour change.
     */
    public function stats(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'group_by' => ['nullable', Rule::in(['code', 'chapter'])],
            'site_id' => ['nullable', 'integer'],
            'sex' => ['nullable', Rule::in(['M', 'F'])],
            'age_min' => ['nullable', 'integer', 'min:0'],
            'age_max' => ['nullable', 'integer', 'min:0'],
        ]);

        $from = isset($data['from']) ? \Illuminate\Support\Carbon::parse($data['from'])->startOfDay() : now()->subMonth()->startOfDay();
        $to = isset($data['to']) ? \Illuminate\Support\Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();
        $groupBy = $data['group_by'] ?? 'code';
        $siteId = $data['site_id'] ?? null;
        $sex = $data['sex'] ?? null;
        $ageMin = $data['age_min'] ?? null;
        $ageMax = $data['age_max'] ?? null;

        $structureId = TenantScope::currentStructureId();
        $cacheKey = sprintf(
            'icd_stats.%s.%s.%s.%s.%s.%s.%s.%s',
            $structureId ?? 'none',
            $from->toDateString(),
            $to->toDateString(),
            $groupBy,
            $siteId ?? 'all',
            $sex ?? 'all',
            $ageMin ?? 'none',
            $ageMax ?? 'none',
        );

        $stats = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($from, $to, $groupBy, $siteId, $sex, $ageMin, $ageMax) {
            $query = ConsultationDiagnosis::query()
                ->whereBetween('consultation_diagnoses.created_at', [$from, $to]);

            if ($siteId || $sex || $ageMin || $ageMax) {
                $query->join('consultations', 'consultations.id', '=', 'consultation_diagnoses.consultation_id')
                    ->when($siteId, fn ($q, $id) => $q->where('consultations.site_id', $id));

                if ($sex || $ageMin || $ageMax) {
                    $query->join('patients', 'patients.id', '=', 'consultations.patient_id')
                        ->when($sex, fn ($q, $s) => $q->where('patients.sex', $s))
                        ->when($ageMin, fn ($q, $min) => $q->where('patients.birth_date', '<=', now()->subYears($min)->toDateString()))
                        ->when($ageMax, fn ($q, $max) => $q->where('patients.birth_date', '>', now()->subYears($max + 1)->toDateString()));
                }
            }

            if ($groupBy === 'chapter') {
                return $query
                    ->join('icd_codes', 'icd_codes.id', '=', 'consultation_diagnoses.icd_code_id')
                    ->join('icd_codes as chapters', 'chapters.id', '=', 'icd_codes.parent_id')
                    ->selectRaw('chapters.code as chapter_code, chapters.label as chapter_label, count(*) as total')
                    ->groupBy('chapters.code', 'chapters.label')
                    ->orderByDesc('total')
                    ->get();
            }

            return $query
                ->selectRaw('consultation_diagnoses.code_snapshot as code, consultation_diagnoses.label_snapshot as label, count(*) as total')
                ->groupBy('consultation_diagnoses.code_snapshot', 'consultation_diagnoses.label_snapshot')
                ->orderByDesc('total')
                ->get();
        });

        return response()->json([
            'from' => \Illuminate\Support\Carbon::parse($from)->toDateString(),
            'to' => \Illuminate\Support\Carbon::parse($to)->toDateString(),
            'group_by' => $groupBy,
            'site_id' => $siteId,
            'sex' => $sex,
            'age_min' => $ageMin,
            'age_max' => $ageMax,
            'stats' => $stats,
        ]);
    }
}
