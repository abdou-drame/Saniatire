<?php

namespace App\Http\Controllers\Api;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Ophtalmo\Models\OphtalmoRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\OphtalmoRecordRequest;
use App\Http\Resources\OphtalmoRecordResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class OphtalmoRecordController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:ophtalmo.view', only: ['index', 'show', 'stats']),
            new Middleware('permission:ophtalmo.create', only: ['store']),
            new Middleware('permission:ophtalmo.update', only: ['update']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $records = OphtalmoRecord::query()
            ->when($request->integer('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->orderByDesc('created_at')
            ->paginate();

        return OphtalmoRecordResource::collection($records)->response();
    }

    public function store(OphtalmoRecordRequest $request): JsonResponse
    {
        $record = OphtalmoRecord::create($request->validated())->refresh();

        if ($record->consultation_id) {
            Consultation::whereKey($record->consultation_id)->update(['specialty_type' => OphtalmoRecord::specialtyType()]);
        }

        return (new OphtalmoRecordResource($record))->response()->setStatusCode(201);
    }

    public function show(OphtalmoRecord $ophtalmoRecord): OphtalmoRecordResource
    {
        return new OphtalmoRecordResource($ophtalmoRecord);
    }

    public function update(OphtalmoRecordRequest $request, OphtalmoRecord $ophtalmoRecord): OphtalmoRecordResource
    {
        $ophtalmoRecord->update($request->validated());

        return new OphtalmoRecordResource($ophtalmoRecord);
    }

    /**
     * Basic count over a period — the seed for a future ophtalmo
     * dashboard, not a dashboard itself.
     */
    public function stats(Request $request): JsonResponse
    {
        $records = OphtalmoRecord::query()
            ->when($request->date('from'), fn ($q, $d) => $q->where('examined_at', '>=', $d))
            ->when($request->date('to'), fn ($q, $d) => $q->where('examined_at', '<=', $d))
            ->get();

        return response()->json(['data' => [
            'total_exams' => $records->count(),
        ]]);
    }
}
