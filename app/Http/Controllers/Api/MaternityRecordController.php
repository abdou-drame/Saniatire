<?php

namespace App\Http\Controllers\Api;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Maternite\Models\MaternityDelivery;
use App\Domain\Maternite\Models\MaternityPartogram;
use App\Domain\Maternite\Models\MaternityRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\MaternityDeliveryRequest;
use App\Http\Requests\MaternityNewbornRequest;
use App\Http\Requests\MaternityPartogramReadingRequest;
use App\Http\Requests\MaternityPartogramRequest;
use App\Http\Requests\MaternityPostpartumVisitRequest;
use App\Http\Requests\MaternityPrenatalVisitRequest;
use App\Http\Requests\MaternityRecordRequest;
use App\Http\Resources\MaternityDeliveryResource;
use App\Http\Resources\MaternityNewbornResource;
use App\Http\Resources\MaternityPartogramReadingResource;
use App\Http\Resources\MaternityPartogramResource;
use App\Http\Resources\MaternityPostpartumVisitResource;
use App\Http\Resources\MaternityPrenatalVisitResource;
use App\Http\Resources\MaternityRecordResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class MaternityRecordController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:maternite.view', only: ['index', 'show', 'stats']),
            new Middleware('permission:maternite.create', only: [
                'store', 'storePrenatalVisit', 'storePartogram', 'storePartogramReading', 'storePostpartumVisit',
            ]),
            new Middleware('permission:maternite.update', only: ['update']),
            // Attending a delivery / recording a newborn is a distinct
            // clinical act from routine CPN/postpartum follow-up — gated
            // behind maternite.validate so e.g. sage_femme (routine
            // follow-up) is boundaried from gynecologue (delivery).
            new Middleware('permission:maternite.validate', only: ['storeDelivery', 'storeNewborn']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $records = MaternityRecord::query()
            ->when($request->integer('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->orderByDesc('created_at')
            ->paginate();

        return MaternityRecordResource::collection($records)->response();
    }

    public function store(MaternityRecordRequest $request): JsonResponse
    {
        $record = MaternityRecord::create($request->validated())->refresh();

        if ($record->consultation_id) {
            Consultation::whereKey($record->consultation_id)->update(['specialty_type' => MaternityRecord::specialtyType()]);
        }

        return (new MaternityRecordResource($record))->response()->setStatusCode(201);
    }

    public function show(MaternityRecord $maternityRecord): MaternityRecordResource
    {
        return new MaternityRecordResource($maternityRecord->load([
            'prenatalVisits', 'partogram.readings', 'delivery.newborns', 'postpartumVisits',
        ]));
    }

    public function update(MaternityRecordRequest $request, MaternityRecord $maternityRecord): MaternityRecordResource
    {
        $maternityRecord->update($request->validated());

        return new MaternityRecordResource($maternityRecord);
    }

    public function storePrenatalVisit(MaternityPrenatalVisitRequest $request, MaternityRecord $maternityRecord): JsonResponse
    {
        $visit = $maternityRecord->prenatalVisits()->create([
            ...$request->validated(),
            'practitioner_id' => $request->user()->id,
        ]);

        return (new MaternityPrenatalVisitResource($visit))->response()->setStatusCode(201);
    }

    public function storePartogram(MaternityPartogramRequest $request, MaternityRecord $maternityRecord): JsonResponse
    {
        abort_if($maternityRecord->partogram()->exists(), 422, 'Un partogramme existe déjà pour cette grossesse.');

        $partogram = $maternityRecord->partogram()->create($request->validated())->refresh();

        return (new MaternityPartogramResource($partogram))->response()->setStatusCode(201);
    }

    public function storePartogramReading(MaternityPartogramReadingRequest $request, MaternityPartogram $partogram): JsonResponse
    {
        // MaternityPartogram carries no structure_id of its own — route
        // binding alone won't reject another structure's id, so tenant
        // scoping must be checked explicitly through its parent record.
        abort_unless($partogram->maternityRecord()->exists(), 404);

        $reading = $partogram->readings()->create($request->validated());

        return (new MaternityPartogramReadingResource($reading))->response()->setStatusCode(201);
    }

    public function storeDelivery(MaternityDeliveryRequest $request, MaternityRecord $maternityRecord): JsonResponse
    {
        abort_if($maternityRecord->delivery()->exists(), 422, 'Un accouchement est déjà enregistré pour cette grossesse.');

        $delivery = $maternityRecord->delivery()->create([
            ...$request->validated(),
            'practitioner_id' => $request->user()->id,
        ]);

        $maternityRecord->update(['status' => 'accouchee']);

        return (new MaternityDeliveryResource($delivery))->response()->setStatusCode(201);
    }

    public function storeNewborn(MaternityNewbornRequest $request, MaternityDelivery $delivery): JsonResponse
    {
        // Same as storePartogramReading: MaternityDelivery has no
        // structure_id of its own, so check tenancy through its parent.
        abort_unless($delivery->maternityRecord()->exists(), 404);

        $newborn = $delivery->newborns()->create($request->validated());

        return (new MaternityNewbornResource($newborn))->response()->setStatusCode(201);
    }

    public function storePostpartumVisit(MaternityPostpartumVisitRequest $request, MaternityRecord $maternityRecord): JsonResponse
    {
        $visit = $maternityRecord->postpartumVisits()->create([
            ...$request->validated(),
            'practitioner_id' => $request->user()->id,
        ])->refresh();

        return (new MaternityPostpartumVisitResource($visit))->response()->setStatusCode(201);
    }

    /**
     * Basic per-period breakdown of delivery modes — the seed for a
     * future maternity dashboard, not a dashboard itself.
     */
    public function stats(Request $request): JsonResponse
    {
        $deliveries = MaternityDelivery::query()
            ->whereHas('maternityRecord')
            ->when($request->date('from'), fn ($q, $d) => $q->where('delivered_at', '>=', $d))
            ->when($request->date('to'), fn ($q, $d) => $q->where('delivered_at', '<=', $d))
            ->get();

        return response()->json(['data' => [
            'total_deliveries' => $deliveries->count(),
            'by_mode' => $deliveries->countBy('mode'),
        ]]);
    }
}
