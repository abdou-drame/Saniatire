<?php

namespace App\Http\Controllers\Api;

use App\Domain\BlocOperatoire\Models\SurgicalProcedure;
use App\Domain\Shared\Billing\BillingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\SurgicalChecklistRequest;
use App\Http\Requests\SurgicalProcedureRequest;
use App\Http\Resources\SurgicalChecklistResource;
use App\Http\Resources\SurgicalProcedureResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SurgicalProcedureController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:bloc_operatoire.view', only: ['index', 'show']),
            new Middleware('permission:bloc_operatoire.create', only: ['store']),
            new Middleware('permission:bloc_operatoire.update', only: ['start', 'complete']),
            new Middleware('permission:bloc_operatoire.cancel', only: ['cancel']),
            new Middleware('permission:bloc_operatoire.validate', only: ['submitChecklistStep']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $procedures = SurgicalProcedure::query()
            ->with(['patient:id,first_name,last_name,patient_number', 'surgeon', 'anesthesiologist'])
            ->when($request->integer('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('scheduled_at')
            ->paginate();

        return SurgicalProcedureResource::collection($procedures)->response();
    }

    public function store(SurgicalProcedureRequest $request): JsonResponse
    {
        $procedure = SurgicalProcedure::create([...$request->validated(), 'status' => 'planifiee'])->refresh();

        return (new SurgicalProcedureResource($procedure))->response()->setStatusCode(201);
    }

    public function show(SurgicalProcedure $surgicalProcedure): SurgicalProcedureResource
    {
        return new SurgicalProcedureResource($surgicalProcedure->load([
            'checklists',
            'patient:id,first_name,last_name,patient_number',
            'surgeon',
            'anesthesiologist',
        ]));
    }

    public function start(SurgicalProcedure $surgicalProcedure): SurgicalProcedureResource
    {
        abort_if($surgicalProcedure->status !== 'planifiee', 422, 'Seule une intervention planifiée peut être démarrée.');

        $surgicalProcedure->update(['status' => 'en_cours']);

        return new SurgicalProcedureResource($surgicalProcedure);
    }

    /**
     * Blocking rule (§4/§7 cahier des charges, point le plus sensible) :
     * impossible de passer à "terminée" tant que les 3 étapes de la
     * checklist n'ont pas toutes été validées. Voir
     * SurgicalProcedure::hasCompleteChecklist().
     */
    public function complete(SurgicalProcedure $surgicalProcedure): SurgicalProcedureResource
    {
        abort_if($surgicalProcedure->status !== 'en_cours', 422, 'Seule une intervention en cours peut être terminée.');
        abort_if(! $surgicalProcedure->hasCompleteChecklist(), 422, 'Les 3 étapes de la checklist doivent être validées avant de terminer l\'intervention.');

        $surgicalProcedure->update([
            'status' => 'terminee',
            'performed_at' => now(),
        ]);

        app(BillingService::class)->recordService($surgicalProcedure);

        return new SurgicalProcedureResource($surgicalProcedure);
    }

    public function cancel(SurgicalProcedure $surgicalProcedure): SurgicalProcedureResource
    {
        abort_if(in_array($surgicalProcedure->status, ['terminee', 'annulee'], true), 422, 'Cette intervention ne peut plus être annulée.');

        $surgicalProcedure->update(['status' => 'annulee']);

        return new SurgicalProcedureResource($surgicalProcedure);
    }

    public function submitChecklistStep(SurgicalChecklistRequest $request, SurgicalProcedure $surgicalProcedure, string $step): JsonResponse
    {
        abort_if(in_array($surgicalProcedure->status, ['terminee', 'annulee'], true), 422, 'Cette intervention est déjà clôturée.');

        $checklist = $surgicalProcedure->checklists()->updateOrCreate(
            ['step' => $step],
            [
                'items' => $request->validated('items'),
                'validated_by' => $request->user()->id,
                'validated_at' => now(),
            ]
        );

        return (new SurgicalChecklistResource($checklist))->response()->setStatusCode(201);
    }
}
