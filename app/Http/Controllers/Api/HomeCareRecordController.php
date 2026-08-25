<?php

namespace App\Http\Controllers\Api;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\SoinsDomicile\Models\HomeCareRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\HomeCareRecordRequest;
use App\Http\Requests\HomeCareVisitRequest;
use App\Http\Resources\HomeCareRecordResource;
use App\Http\Resources\HomeCareVisitResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Planning the next passage(s) deliberately reuses the existing
 * appointments module (POST /appointments with the patient_id) rather
 * than a bespoke scheduler here — a home-care visit's "next passage" is
 * the same concept as any other future appointment, and the socle
 * already has conflict-free slot handling for it.
 */
class HomeCareRecordController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:soins_domicile.view', only: ['index', 'show', 'stats']),
            new Middleware('permission:soins_domicile.create', only: ['store', 'storeVisit']),
            new Middleware('permission:soins_domicile.update', only: ['update']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $records = HomeCareRecord::query()
            ->when($request->integer('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->orderByDesc('created_at')
            ->paginate();

        return HomeCareRecordResource::collection($records)->response();
    }

    public function store(HomeCareRecordRequest $request): JsonResponse
    {
        $record = HomeCareRecord::create($request->validated())->refresh();

        if ($record->consultation_id) {
            Consultation::whereKey($record->consultation_id)->update(['specialty_type' => HomeCareRecord::specialtyType()]);
        }

        return (new HomeCareRecordResource($record))->response()->setStatusCode(201);
    }

    public function show(HomeCareRecord $homeCareRecord): HomeCareRecordResource
    {
        return new HomeCareRecordResource($homeCareRecord->load('visits'));
    }

    public function update(HomeCareRecordRequest $request, HomeCareRecord $homeCareRecord): HomeCareRecordResource
    {
        $homeCareRecord->update($request->validated());

        return new HomeCareRecordResource($homeCareRecord);
    }

    public function storeVisit(HomeCareVisitRequest $request, HomeCareRecord $homeCareRecord): JsonResponse
    {
        $visit = $homeCareRecord->visits()->create([
            ...$request->validated(),
            'intervenant_id' => $request->user()->id,
        ]);

        return (new HomeCareVisitResource($visit))->response()->setStatusCode(201);
    }

    /**
     * Basic visit count over a period — the seed for a future soins à
     * domicile dashboard, not a dashboard itself.
     */
    public function stats(Request $request): JsonResponse
    {
        $visits = \App\Domain\SoinsDomicile\Models\HomeCareVisit::query()
            ->whereHas('homeCareRecord')
            ->when($request->date('from'), fn ($q, $d) => $q->where('visit_datetime', '>=', $d))
            ->when($request->date('to'), fn ($q, $d) => $q->where('visit_datetime', '<=', $d))
            ->get();

        return response()->json(['data' => [
            'total_visits' => $visits->count(),
        ]]);
    }
}
