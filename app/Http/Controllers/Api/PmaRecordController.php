<?php

namespace App\Http\Controllers\Api;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Pma\Models\PmaRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\PmaCycleMonitoringRequest;
use App\Http\Requests\PmaRecordRequest;
use App\Http\Requests\PmaStimulationProtocolRequest;
use App\Http\Resources\PmaCycleMonitoringResource;
use App\Http\Resources\PmaRecordResource;
use App\Http\Resources\PmaStimulationProtocolResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PmaRecordController extends Controller implements HasMiddleware
{
    /**
     * Highly sensitive data: gated behind pma.* permissions which, unlike
     * every other specialty, are granted only to specialiste_pma and
     * directeur_medical (see RolePermissionSeeder) — no other clinical
     * role or the non-medical direction role can reach any action here.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:pma.view', only: ['index', 'show', 'stats']),
            new Middleware('permission:pma.create', only: ['store', 'storeStimulationProtocol', 'storeCycleMonitoring']),
            new Middleware('permission:pma.update', only: ['update']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $records = PmaRecord::query()
            ->when($request->integer('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->orderByDesc('created_at')
            ->paginate();

        $this->logSensitiveAccess($request, 'liste des dossiers PMA consultée', ['patient_id' => $request->integer('patient_id')]);

        return PmaRecordResource::collection($records)->response();
    }

    public function store(PmaRecordRequest $request): JsonResponse
    {
        $data = $request->validated();
        // Le champ est optionnel a la saisie (le formulaire n'impose aucun
        // resultat de tentative a la creation) : quand il est absent, on
        // laisse la colonne appliquer son defaut ('en_cours') plutot que
        // d'inserer un NULL explicite qui violerait sa contrainte NOT NULL.
        if (! isset($data['attempt_result'])) {
            unset($data['attempt_result']);
        }

        $record = PmaRecord::create($data)->refresh();

        if ($record->consultation_id) {
            Consultation::whereKey($record->consultation_id)->update(['specialty_type' => PmaRecord::specialtyType()]);
        }

        return (new PmaRecordResource($record))->response()->setStatusCode(201);
    }

    public function show(Request $request, PmaRecord $pmaRecord): PmaRecordResource
    {
        $this->logSensitiveAccess($request, 'dossier PMA consulté', ['pma_record_id' => $pmaRecord->id]);

        return new PmaRecordResource($pmaRecord->load('stimulationProtocols', 'cycleMonitorings'));
    }

    public function update(PmaRecordRequest $request, PmaRecord $pmaRecord): PmaRecordResource
    {
        $pmaRecord->update($request->validated());

        return new PmaRecordResource($pmaRecord);
    }

    public function storeStimulationProtocol(PmaStimulationProtocolRequest $request, PmaRecord $pmaRecord): JsonResponse
    {
        $protocol = $pmaRecord->stimulationProtocols()->create($request->validated());

        return (new PmaStimulationProtocolResource($protocol))->response()->setStatusCode(201);
    }

    public function storeCycleMonitoring(PmaCycleMonitoringRequest $request, PmaRecord $pmaRecord): JsonResponse
    {
        $monitoring = $pmaRecord->cycleMonitorings()->create($request->validated());

        return (new PmaCycleMonitoringResource($monitoring))->response()->setStatusCode(201);
    }

    /**
     * Basic count over a period — the seed for a future PMA dashboard,
     * not a dashboard itself.
     */
    public function stats(Request $request): JsonResponse
    {
        $records = PmaRecord::query()
            ->when($request->date('from'), fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->date('to'), fn ($q, $d) => $q->where('created_at', '<=', $d))
            ->get();

        return response()->json(['data' => [
            'total_records' => $records->count(),
            'by_result' => $records->countBy('attempt_result'),
        ]]);
    }

    /**
     * Reinforced audit trail (§3 confidentialité renforcée): every successful
     * read of PMA data is traced, mirroring the denied-access logging in
     * bootstrap/app.php's UnauthorizedException renderable().
     */
    private function logSensitiveAccess(Request $request, string $description, array $properties = []): void
    {
        activity('acces_sensible')
            ->causedBy($request->user())
            ->withProperties(array_merge($properties, ['resultat' => 'autorise', 'route' => $request->path()]))
            ->log($description);
    }
}
