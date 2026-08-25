<?php

namespace App\Http\Controllers\Api;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\MedecineTravail\Models\OccupationalHealthRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\OccupationalHealthRecordRequest;
use App\Http\Resources\OccupationalHealthRecordResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class OccupationalHealthRecordController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medecine_travail.view', only: ['index', 'show', 'stats']),
            new Middleware('permission:medecine_travail.create', only: ['store']),
            new Middleware('permission:medecine_travail.update', only: ['update']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $records = OccupationalHealthRecord::query()
            ->when($request->integer('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->orderByDesc('created_at')
            ->paginate();

        return OccupationalHealthRecordResource::collection($records)->response();
    }

    public function store(OccupationalHealthRecordRequest $request): JsonResponse
    {
        $record = OccupationalHealthRecord::create($request->validated())->refresh();

        if ($record->consultation_id) {
            Consultation::whereKey($record->consultation_id)->update(['specialty_type' => OccupationalHealthRecord::specialtyType()]);
        }

        return (new OccupationalHealthRecordResource($record))->response()->setStatusCode(201);
    }

    public function show(OccupationalHealthRecord $occupationalHealthRecord): OccupationalHealthRecordResource
    {
        return new OccupationalHealthRecordResource($occupationalHealthRecord);
    }

    public function update(OccupationalHealthRecordRequest $request, OccupationalHealthRecord $occupationalHealthRecord): OccupationalHealthRecordResource
    {
        $occupationalHealthRecord->update($request->validated());

        return new OccupationalHealthRecordResource($occupationalHealthRecord);
    }

    /**
     * Basic breakdown by fitness status over a period — the seed for a
     * future médecine du travail dashboard, not a dashboard itself.
     */
    public function stats(Request $request): JsonResponse
    {
        $records = OccupationalHealthRecord::query()
            ->when($request->date('from'), fn ($q, $d) => $q->where('visit_date', '>=', $d))
            ->when($request->date('to'), fn ($q, $d) => $q->where('visit_date', '<=', $d))
            ->get();

        return response()->json(['data' => [
            'total_visits' => $records->count(),
            'by_fitness_status' => $records->countBy('fitness_status'),
        ]]);
    }
}
