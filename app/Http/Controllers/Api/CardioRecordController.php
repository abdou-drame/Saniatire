<?php

namespace App\Http\Controllers\Api;

use App\Domain\Cardiologie\Models\CardioRecord;
use App\Domain\Consultation\Models\Consultation;
use App\Http\Controllers\Controller;
use App\Http\Requests\CardioEcgResultRequest;
use App\Http\Requests\CardioReadingRequest;
use App\Http\Requests\CardioRecordRequest;
use App\Http\Resources\CardioEcgResultResource;
use App\Http\Resources\CardioReadingResource;
use App\Http\Resources\CardioRecordResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CardioRecordController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:cardiologie.view', only: ['index', 'show', 'stats']),
            new Middleware('permission:cardiologie.create', only: ['store', 'storeReading', 'storeEcgResult']),
            new Middleware('permission:cardiologie.update', only: ['update']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $records = CardioRecord::query()
            ->when($request->integer('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->orderByDesc('created_at')
            ->paginate();

        return CardioRecordResource::collection($records)->response();
    }

    public function store(CardioRecordRequest $request): JsonResponse
    {
        $record = CardioRecord::create($request->validated())->refresh();

        if ($record->consultation_id) {
            Consultation::whereKey($record->consultation_id)->update(['specialty_type' => CardioRecord::specialtyType()]);
        }

        return (new CardioRecordResource($record))->response()->setStatusCode(201);
    }

    public function show(CardioRecord $cardioRecord): CardioRecordResource
    {
        return new CardioRecordResource($cardioRecord->load('readings', 'ecgResults'));
    }

    public function update(CardioRecordRequest $request, CardioRecord $cardioRecord): CardioRecordResource
    {
        $cardioRecord->update($request->validated());

        return new CardioRecordResource($cardioRecord);
    }

    public function storeReading(CardioReadingRequest $request, CardioRecord $cardioRecord): JsonResponse
    {
        $reading = $cardioRecord->readings()->create($request->validated());

        return (new CardioReadingResource($reading))->response()->setStatusCode(201);
    }

    public function storeEcgResult(CardioEcgResultRequest $request, CardioRecord $cardioRecord): JsonResponse
    {
        $ecg = $cardioRecord->ecgResults()->create($request->validated());

        return (new CardioEcgResultResource($ecg))->response()->setStatusCode(201);
    }

    /**
     * Basic count over a period — the seed for a future cardio dashboard,
     * not a dashboard itself.
     */
    public function stats(Request $request): JsonResponse
    {
        $records = CardioRecord::query()
            ->when($request->date('from'), fn ($q, $d) => $q->where('examined_at', '>=', $d))
            ->when($request->date('to'), fn ($q, $d) => $q->where('examined_at', '<=', $d))
            ->get();

        return response()->json(['data' => [
            'total_records' => $records->count(),
        ]]);
    }
}
