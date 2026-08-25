<?php

namespace App\Http\Controllers\Api;

use App\Domain\Appointment\Models\Appointment;
use App\Domain\Consultation\Models\Consultation;
use App\Domain\Shared\Billing\BillingService;
use App\Domain\Teleconsultation\Events\TeleconsultationPlanifiee;
use App\Domain\Teleconsultation\Models\Teleconsultation;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConsultationResource;
use App\Http\Resources\TeleconsultationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TeleconsultationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:teleconsultation.view', only: ['index', 'show']),
            new Middleware('permission:teleconsultation.create', only: ['store']),
            new Middleware('permission:teleconsultation.update', only: ['start', 'close']),
            new Middleware('permission:teleconsultation.cancel', only: ['cancel']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $teleconsultations = Teleconsultation::query()
            ->when($request->integer('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->orderByDesc('created_at')
            ->paginate();

        return TeleconsultationResource::collection($teleconsultations)->response();
    }

    public function show(Teleconsultation $teleconsultation): TeleconsultationResource
    {
        return new TeleconsultationResource($teleconsultation);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'appointment_id' => ['required', 'integer', 'exists:appointments,id'],
        ]);

        $appointment = Appointment::findOrFail($data['appointment_id']);

        $teleconsultation = Teleconsultation::create([
            'site_id' => $appointment->site_id,
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'practitioner_id' => $appointment->practitioner_id,
            'statut' => 'planifiee',
        ])->refresh();

        TeleconsultationPlanifiee::dispatch($teleconsultation);

        return (new TeleconsultationResource($teleconsultation))->response()->setStatusCode(201);
    }

    public function start(Teleconsultation $teleconsultation): TeleconsultationResource
    {
        abort_if(in_array($teleconsultation->statut, ['terminee', 'annulee'], true), 422, 'Cette téléconsultation ne peut plus démarrer.');

        $teleconsultation->update(['statut' => 'en_cours', 'started_at' => now()]);

        return new TeleconsultationResource($teleconsultation);
    }

    /**
     * Clôture = mêmes champs cliniques qu'une consultation classique
     * (voir ConsultationRequest), créée puis clôturée exactement comme
     * ConsultationController::close() — pour apparaître à l'identique
     * dans la timeline patient (PatientController::timeline() ne filtre
     * que sur status=terminee, téléconsultation ou non).
     */
    public function close(Request $request, Teleconsultation $teleconsultation): ConsultationResource
    {
        abort_if(in_array($teleconsultation->statut, ['terminee', 'annulee'], true), 422, 'Cette téléconsultation est déjà clôturée ou annulée.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'history_of_illness' => ['nullable', 'string'],
            'clinical_exam' => ['nullable', 'string'],
            'recommendations' => ['nullable', 'string'],
            'referral' => ['nullable', 'string', 'max:255'],
            'follow_up_suggested_at' => ['nullable', 'date'],
        ]);

        $consultation = Consultation::create([
            'patient_id' => $teleconsultation->patient_id,
            'practitioner_id' => $teleconsultation->practitioner_id,
            'site_id' => $teleconsultation->site_id,
            'appointment_id' => $teleconsultation->appointment_id,
            ...$data,
        ])->refresh();

        $consultation->update(['status' => 'terminee', 'closed_at' => now()]);

        app(BillingService::class)->recordService($consultation);

        $teleconsultation->update([
            'consultation_id' => $consultation->id,
            'statut' => 'terminee',
            'ended_at' => now(),
        ]);

        return new ConsultationResource($consultation->load('diagnoses'));
    }

    public function cancel(Teleconsultation $teleconsultation): TeleconsultationResource
    {
        abort_if($teleconsultation->statut === 'terminee', 422, 'Cette téléconsultation est déjà clôturée.');

        $teleconsultation->update(['statut' => 'annulee']);

        return new TeleconsultationResource($teleconsultation);
    }
}
