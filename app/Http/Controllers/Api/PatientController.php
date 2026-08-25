<?php

namespace App\Http\Controllers\Api;

use App\Domain\BlocOperatoire\Models\SurgicalProcedure;
use App\Domain\Hospitalisation\Models\Hospitalization;
use App\Domain\Imagerie\Models\ImagingOrder;
use App\Domain\Laboratoire\Models\LabOrder;
use App\Domain\Patient\Models\Patient;
use App\Domain\Shared\Auth\PortalActivationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\PatientRequest;
use App\Http\Resources\ConsultationResource;
use App\Http\Resources\HospitalizationResource;
use App\Http\Resources\ImagingOrderResource;
use App\Http\Resources\LabOrderResource;
use App\Http\Resources\PatientResource;
use App\Http\Resources\SurgicalProcedureResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Activitylog\Models\Activity;

class PatientController extends Controller implements HasMiddleware
{
    public function __construct(private readonly PortalActivationService $activationService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:patients.view', only: ['index', 'show']),
            new Middleware('permission:patients.create', only: ['store']),
            new Middleware('permission:patients.update', only: ['update', 'sendPortalActivation']),
            new Middleware('permission:patients.delete', only: ['destroy']),
            new Middleware('permission:patients_medical.view', only: ['timeline']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $patients = Patient::query()
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request) {
                // LOWER(...) LIKE ? instead of Postgres-only ILIKE so this
                // stays portable to the SQLite connection the test suite
                // runs against — same pattern as IcdCodeController::index.
                $term = '%'.mb_strtolower($request->string('search')->toString()).'%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(first_name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(patient_number) LIKE ?', [$term])
                        ->orWhere('phone', 'like', $term);
                });
            })
            ->orderBy('last_name')
            ->paginate();

        return PatientResource::collection($patients)->response();
    }

    public function store(PatientRequest $request): JsonResponse
    {
        $data = $request->validated();

        $patient = Patient::create($data);

        $duplicates = Patient::query()
            ->where('id', '!=', $patient->id)
            ->whereRaw('lower(first_name) = ?', [mb_strtolower($data['first_name'])])
            ->whereRaw('lower(last_name) = ?', [mb_strtolower($data['last_name'])])
            ->whereDate('birth_date', $data['birth_date'])
            ->get(['id', 'patient_number']);

        return (new PatientResource($patient))
            ->additional(['possible_duplicates' => $duplicates])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Patient $patient): PatientResource
    {
        if ($request->user()->can('patients_medical.view')) {
            $patient->load('medicalInfo', 'allergies');
        }

        return new PatientResource($patient);
    }

    public function update(PatientRequest $request, Patient $patient): PatientResource
    {
        $patient->update($request->validated());

        return new PatientResource($patient);
    }

    public function destroy(Patient $patient): JsonResponse
    {
        $patient->delete();

        return response()->json(null, 204);
    }

    /**
     * Envoie (ou renvoie) le lien d'activation du portail patient — étape
     * 7b §1. Réutilise directement le système de notifications multicanal
     * de l'étape 7a via PortalActivationService.
     */
    public function sendPortalActivation(Patient $patient): JsonResponse
    {
        abort_if($patient->portal_activated_at !== null, 422, 'Le portail de ce patient est déjà activé.');
        abort_if(! $patient->email, 422, "Ce patient n'a pas d'adresse email enregistrée.");

        $this->activationService->createFor($patient);

        return response()->json(['message' => "Lien d'activation envoyé."]);
    }

    /**
     * No stored/materialized timeline table exists in the socle — this
     * builds one at query time from closed consultations (the clinical
     * events) merged with the patient's own audit trail (administrative
     * events: creation, updates, ...), newest first. If this becomes a
     * performance concern under real volume, revisit as a materialized
     * view rather than changing the API shape.
     */
    public function timeline(Patient $patient): JsonResponse
    {
        $consultations = $patient->consultations()
            ->where('status', 'terminee')
            ->with('diagnoses')
            ->get()
            ->map(fn ($consultation) => [
                'type' => 'consultation',
                'date' => $consultation->closed_at,
                'summary' => $consultation->reason,
                'data' => new ConsultationResource($consultation),
            ]);

        $activities = Activity::query()
            ->where('subject_type', Patient::class)
            ->where('subject_id', $patient->id)
            ->get()
            ->map(fn (Activity $activity) => [
                'type' => 'activity',
                'date' => $activity->created_at,
                'summary' => $activity->description,
                'data' => $activity,
            ]);

        $labOrders = LabOrder::where('patient_id', $patient->id)
            ->where('status', 'transmis')
            ->with(['items.loincCode', 'items.result'])
            ->get()
            ->map(fn (LabOrder $order) => [
                'type' => 'lab_order',
                'date' => $order->updated_at,
                'summary' => 'Résultats de laboratoire transmis',
                'data' => new LabOrderResource($order),
            ]);

        $imagingOrders = ImagingOrder::where('patient_id', $patient->id)
            ->where('status', 'transmis')
            ->with(['studies.report'])
            ->get()
            ->map(fn (ImagingOrder $order) => [
                'type' => 'imaging_order',
                'date' => $order->updated_at,
                'summary' => 'Compte rendu d\'imagerie transmis',
                'data' => new ImagingOrderResource($order),
            ]);

        $hospitalizations = Hospitalization::where('patient_id', $patient->id)
            ->where('status', 'sorti')
            ->with(['bed', 'ward:id,name', 'attendingPhysician'])
            ->get()
            ->map(fn (Hospitalization $hospitalization) => [
                'type' => 'hospitalization',
                'date' => $hospitalization->discharged_at,
                'summary' => 'Hospitalisation terminée',
                'data' => new HospitalizationResource($hospitalization),
            ]);

        $surgicalProcedures = SurgicalProcedure::where('patient_id', $patient->id)
            ->where('status', 'terminee')
            ->with(['surgeon', 'anesthesiologist'])
            ->get()
            ->map(fn (SurgicalProcedure $procedure) => [
                'type' => 'surgical_procedure',
                'date' => $procedure->performed_at ?? $procedure->updated_at,
                'summary' => 'Intervention chirurgicale réalisée',
                'data' => new SurgicalProcedureResource($procedure),
            ]);

        $timeline = $consultations->concat($activities)
            ->concat($labOrders)
            ->concat($imagingOrders)
            ->concat($hospitalizations)
            ->concat($surgicalProcedures)
            ->sortByDesc('date')
            ->values();

        return response()->json(['data' => $timeline]);
    }
}
