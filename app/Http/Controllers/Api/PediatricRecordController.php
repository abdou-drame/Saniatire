<?php

namespace App\Http\Controllers\Api;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Pediatrie\Models\PediatricRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\PediatricDevelopmentObservationRequest;
use App\Http\Requests\PediatricGrowthMeasurementRequest;
use App\Http\Requests\PediatricRecordRequest;
use App\Http\Requests\PediatricVaccinationRequest;
use App\Http\Resources\PediatricDevelopmentObservationResource;
use App\Http\Resources\PediatricGrowthMeasurementResource;
use App\Http\Resources\PediatricRecordResource;
use App\Http\Resources\PediatricVaccinationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PediatricRecordController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:pediatrie.view', only: ['index', 'show', 'stats']),
            new Middleware('permission:pediatrie.create', only: [
                'store', 'storeGrowthMeasurement', 'storeVaccination', 'storeDevelopmentObservation',
            ]),
            new Middleware('permission:pediatrie.update', only: ['update']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $records = PediatricRecord::query()
            ->when($request->integer('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->orderByDesc('created_at')
            ->paginate();

        return PediatricRecordResource::collection($records)->response();
    }

    public function store(PediatricRecordRequest $request): JsonResponse
    {
        $record = PediatricRecord::create($request->validated())->refresh();

        if ($record->consultation_id) {
            Consultation::whereKey($record->consultation_id)->update(['specialty_type' => PediatricRecord::specialtyType()]);
        }

        return (new PediatricRecordResource($record))->response()->setStatusCode(201);
    }

    public function show(PediatricRecord $pediatricRecord): PediatricRecordResource
    {
        return new PediatricRecordResource($pediatricRecord->load('growthMeasurements', 'vaccinations', 'developmentObservations'));
    }

    public function update(PediatricRecordRequest $request, PediatricRecord $pediatricRecord): PediatricRecordResource
    {
        $pediatricRecord->update($request->validated());

        return new PediatricRecordResource($pediatricRecord);
    }

    public function storeGrowthMeasurement(PediatricGrowthMeasurementRequest $request, PediatricRecord $pediatricRecord): JsonResponse
    {
        $measurement = $pediatricRecord->growthMeasurements()->create($request->validated());

        return (new PediatricGrowthMeasurementResource($measurement))->response()->setStatusCode(201);
    }

    public function storeVaccination(PediatricVaccinationRequest $request, PediatricRecord $pediatricRecord): JsonResponse
    {
        $vaccination = $pediatricRecord->vaccinations()->create($request->validated());

        return (new PediatricVaccinationResource($vaccination))->response()->setStatusCode(201);
    }

    public function storeDevelopmentObservation(PediatricDevelopmentObservationRequest $request, PediatricRecord $pediatricRecord): JsonResponse
    {
        $observation = $pediatricRecord->developmentObservations()->create($request->validated());

        return (new PediatricDevelopmentObservationResource($observation))->response()->setStatusCode(201);
    }

    /**
     * Basic count over a period — the seed for a future pédiatrie
     * dashboard, not a dashboard itself.
     */
    public function stats(Request $request): JsonResponse
    {
        $records = PediatricRecord::query()
            ->when($request->date('from'), fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->date('to'), fn ($q, $d) => $q->where('created_at', '<=', $d))
            ->get();

        return response()->json(['data' => [
            'total_records' => $records->count(),
        ]]);
    }
}
