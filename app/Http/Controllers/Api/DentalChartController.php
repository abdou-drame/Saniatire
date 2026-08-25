<?php

namespace App\Http\Controllers\Api;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Dentaire\Models\DentalChart;
use App\Domain\Dentaire\Models\DentalProcedure;
use App\Domain\Dentaire\Models\DentalTreatmentPlan;
use App\Domain\Dentaire\Models\DentalTreatmentPlanItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\DentalChartRequest;
use App\Http\Requests\DentalProcedureRequest;
use App\Http\Requests\DentalToothStateRequest;
use App\Http\Requests\DentalTreatmentPlanItemRequest;
use App\Http\Resources\DentalChartResource;
use App\Http\Resources\DentalProcedureResource;
use App\Http\Resources\DentalToothStateResource;
use App\Http\Resources\DentalTreatmentPlanItemResource;
use App\Http\Resources\DentalTreatmentPlanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DentalChartController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:dentaire.view', only: ['index', 'show', 'stats']),
            new Middleware('permission:dentaire.create', only: ['store', 'storeProcedure', 'storeTreatmentPlan', 'storeTreatmentPlanItem']),
            new Middleware('permission:dentaire.update', only: ['updateToothState', 'updateTreatmentPlanItem']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $charts = DentalChart::query()
            ->when($request->integer('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->orderByDesc('created_at')
            ->paginate();

        return DentalChartResource::collection($charts)->response();
    }

    public function store(DentalChartRequest $request): JsonResponse
    {
        $chart = DentalChart::create($request->validated())->refresh();

        if ($chart->consultation_id) {
            Consultation::whereKey($chart->consultation_id)->update(['specialty_type' => DentalChart::specialtyType()]);
        }

        return (new DentalChartResource($chart))->response()->setStatusCode(201);
    }

    public function show(DentalChart $dentalChart): DentalChartResource
    {
        return new DentalChartResource($dentalChart->load(['toothStates', 'procedures', 'treatmentPlans.items']));
    }

    /**
     * Upserts a single tooth's state (unique on dental_chart_id + fdi) —
     * updating one tooth never touches the others.
     */
    public function updateToothState(DentalToothStateRequest $request, DentalChart $dentalChart, string $fdi): JsonResponse
    {
        $tooth = $dentalChart->toothStates()->updateOrCreate(
            ['tooth_fdi' => $fdi],
            $request->validated(),
        );

        return (new DentalToothStateResource($tooth))->response()->setStatusCode(200);
    }

    public function storeProcedure(DentalProcedureRequest $request, DentalChart $dentalChart): JsonResponse
    {
        $procedure = $dentalChart->procedures()->create([
            ...$request->validated(),
            'practitioner_id' => $request->user()->id,
        ]);

        return (new DentalProcedureResource($procedure))->response()->setStatusCode(201);
    }

    public function storeTreatmentPlan(Request $request, DentalChart $dentalChart): JsonResponse
    {
        $plan = $dentalChart->treatmentPlans()->create([
            'created_by' => $request->user()->id,
            'status' => 'en_cours',
        ]);

        return (new DentalTreatmentPlanResource($plan))->response()->setStatusCode(201);
    }

    public function storeTreatmentPlanItem(DentalTreatmentPlanItemRequest $request, DentalTreatmentPlan $treatmentPlan): JsonResponse
    {
        // DentalTreatmentPlan carries no structure_id of its own — route
        // binding alone won't reject another structure's id, so tenant
        // scoping must be checked explicitly through its parent chart.
        abort_unless($treatmentPlan->dentalChart()->exists(), 404);

        $item = $treatmentPlan->items()->create($request->validated())->refresh();

        return (new DentalTreatmentPlanItemResource($item))->response()->setStatusCode(201);
    }

    /**
     * Marks a single treatment plan item realized/cancelled (or edits its
     * act) without touching the other items on the same plan.
     */
    public function updateTreatmentPlanItem(DentalTreatmentPlanItemRequest $request, DentalTreatmentPlanItem $item): DentalTreatmentPlanItemResource
    {
        abort_unless($item->treatmentPlan()->whereHas('dentalChart')->exists(), 404);

        $item->update($request->validated());

        return new DentalTreatmentPlanItemResource($item);
    }

    /**
     * Basic breakdown of realized acts by type — the seed for a future
     * dental dashboard, not a dashboard itself.
     */
    public function stats(Request $request): JsonResponse
    {
        $procedures = DentalProcedure::query()
            ->whereHas('dentalChart')
            ->when($request->date('from'), fn ($q, $d) => $q->where('performed_at', '>=', $d))
            ->when($request->date('to'), fn ($q, $d) => $q->where('performed_at', '<=', $d))
            ->get();

        return response()->json(['data' => [
            'total_procedures' => $procedures->count(),
            'by_act_type' => $procedures->countBy('act_type'),
        ]]);
    }
}
