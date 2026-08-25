<?php

namespace App\Http\Controllers\Api;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\SanteMentale\Models\MentalHealthRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\MentalHealthRecordRequest;
use App\Http\Requests\MentalHealthScaleScoreRequest;
use App\Http\Resources\MentalHealthRecordResource;
use App\Http\Resources\MentalHealthScaleScoreResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class MentalHealthRecordController extends Controller implements HasMiddleware
{
    /**
     * Highly sensitive data: gated behind sante_mentale.* permissions
     * which, unlike every other specialty, are granted only to
     * psychiatre/psychologue and directeur_medical (see
     * RolePermissionSeeder) — no other clinical role or the non-medical
     * direction role can reach any action here.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:sante_mentale.view', only: ['index', 'show', 'stats']),
            new Middleware('permission:sante_mentale.create', only: ['store', 'storeScaleScore']),
            new Middleware('permission:sante_mentale.update', only: ['update']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $records = MentalHealthRecord::query()
            ->when($request->integer('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->orderByDesc('created_at')
            ->paginate();

        $this->logSensitiveAccess($request, 'liste des dossiers de santé mentale consultée', ['patient_id' => $request->integer('patient_id')]);

        return MentalHealthRecordResource::collection($records)->response();
    }

    public function store(MentalHealthRecordRequest $request): JsonResponse
    {
        $record = MentalHealthRecord::create($request->validated())->refresh();

        if ($record->consultation_id) {
            Consultation::whereKey($record->consultation_id)->update(['specialty_type' => MentalHealthRecord::specialtyType()]);
        }

        return (new MentalHealthRecordResource($record))->response()->setStatusCode(201);
    }

    public function show(Request $request, MentalHealthRecord $mentalHealthRecord): MentalHealthRecordResource
    {
        $this->logSensitiveAccess($request, 'dossier de santé mentale consulté', ['mental_health_record_id' => $mentalHealthRecord->id]);

        return new MentalHealthRecordResource($mentalHealthRecord->load('scaleScores'));
    }

    public function update(MentalHealthRecordRequest $request, MentalHealthRecord $mentalHealthRecord): MentalHealthRecordResource
    {
        $mentalHealthRecord->update($request->validated());

        return new MentalHealthRecordResource($mentalHealthRecord);
    }

    public function storeScaleScore(MentalHealthScaleScoreRequest $request, MentalHealthRecord $mentalHealthRecord): JsonResponse
    {
        $score = $mentalHealthRecord->scaleScores()->create($request->validated());

        return (new MentalHealthScaleScoreResource($score))->response()->setStatusCode(201);
    }

    /**
     * Basic count over a period — the seed for a future santé mentale
     * dashboard, not a dashboard itself.
     */
    public function stats(Request $request): JsonResponse
    {
        $records = MentalHealthRecord::query()
            ->when($request->date('from'), fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->date('to'), fn ($q, $d) => $q->where('created_at', '<=', $d))
            ->get();

        return response()->json(['data' => [
            'total_records' => $records->count(),
        ]]);
    }

    /**
     * Reinforced audit trail (§3 confidentialité renforcée): every successful
     * read of santé mentale data is traced, mirroring the denied-access
     * logging in bootstrap/app.php's UnauthorizedException renderable().
     */
    private function logSensitiveAccess(Request $request, string $description, array $properties = []): void
    {
        activity('acces_sensible')
            ->causedBy($request->user())
            ->withProperties(array_merge($properties, ['resultat' => 'autorise', 'route' => $request->path()]))
            ->log($description);
    }
}
