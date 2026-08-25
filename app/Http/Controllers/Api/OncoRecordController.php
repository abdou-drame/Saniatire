<?php

namespace App\Http\Controllers\Api;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Oncologie\Models\OncoRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\OncoChemoCycleRequest;
use App\Http\Requests\OncoRecordRequest;
use App\Http\Requests\OncoResponseEvaluationRequest;
use App\Http\Resources\OncoChemoCycleResource;
use App\Http\Resources\OncoRecordResource;
use App\Http\Resources\OncoResponseEvaluationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class OncoRecordController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:oncologie.view', only: ['index', 'show', 'stats']),
            new Middleware('permission:oncologie.create', only: ['store', 'storeChemoCycle', 'storeResponseEvaluation']),
            new Middleware('permission:oncologie.update', only: ['update']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $records = OncoRecord::query()
            ->when($request->integer('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->orderByDesc('created_at')
            ->paginate();

        return OncoRecordResource::collection($records)->response();
    }

    public function store(OncoRecordRequest $request): JsonResponse
    {
        $record = OncoRecord::create($request->validated())->refresh();

        if ($record->consultation_id) {
            Consultation::whereKey($record->consultation_id)->update(['specialty_type' => OncoRecord::specialtyType()]);
        }

        return (new OncoRecordResource($record))->response()->setStatusCode(201);
    }

    public function show(OncoRecord $oncoRecord): OncoRecordResource
    {
        return new OncoRecordResource($oncoRecord->load('chemoCycles', 'responseEvaluations'));
    }

    public function update(OncoRecordRequest $request, OncoRecord $oncoRecord): OncoRecordResource
    {
        $oncoRecord->update($request->validated());

        return new OncoRecordResource($oncoRecord);
    }

    public function storeChemoCycle(OncoChemoCycleRequest $request, OncoRecord $oncoRecord): JsonResponse
    {
        $cycle = $oncoRecord->chemoCycles()->create($request->validated());

        return (new OncoChemoCycleResource($cycle))->response()->setStatusCode(201);
    }

    public function storeResponseEvaluation(OncoResponseEvaluationRequest $request, OncoRecord $oncoRecord): JsonResponse
    {
        $evaluation = $oncoRecord->responseEvaluations()->create($request->validated());

        return (new OncoResponseEvaluationResource($evaluation))->response()->setStatusCode(201);
    }

    /**
     * Basic count over a period — the seed for a future oncologie
     * dashboard, not a dashboard itself.
     */
    public function stats(Request $request): JsonResponse
    {
        $records = OncoRecord::query()
            ->when($request->date('from'), fn ($q, $d) => $q->where('diagnosed_at', '>=', $d))
            ->when($request->date('to'), fn ($q, $d) => $q->where('diagnosed_at', '<=', $d))
            ->get();

        return response()->json(['data' => [
            'total_records' => $records->count(),
            'by_cancer_type' => $records->countBy('cancer_type'),
        ]]);
    }
}
