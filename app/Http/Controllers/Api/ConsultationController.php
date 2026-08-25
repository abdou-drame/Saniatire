<?php

namespace App\Http\Controllers\Api;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Consultation\Models\ConsultationDiagnosis;
use App\Domain\Icd\Models\IcdCode;
use App\Domain\Shared\Billing\BillingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultationDiagnosisRequest;
use App\Http\Requests\ConsultationRequest;
use App\Http\Resources\ConsultationDiagnosisResource;
use App\Http\Resources\ConsultationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class ConsultationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:consultations.view', only: ['index', 'show']),
            new Middleware('permission:consultations.create', only: ['store']),
            new Middleware('permission:consultations.update', only: ['update']),
            // Closing and coding a diagnosis both require clinical
            // judgment, unlike recording vitals (consultations.update) —
            // kept behind the stricter "validate" permission so e.g. a
            // nurse can enter constants but not finalize a diagnosis.
            new Middleware('permission:consultations.validate', only: ['close', 'storeDiagnosis', 'updateDiagnosis']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $consultations = Consultation::query()
            ->with('diagnoses')
            ->when($request->integer('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->when($request->integer('practitioner_id'), fn ($q, $id) => $q->where('practitioner_id', $id))
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->paginate();

        return ConsultationResource::collection($consultations)->response();
    }

    public function store(ConsultationRequest $request): JsonResponse
    {
        $consultation = Consultation::create($request->validated())->refresh();

        return (new ConsultationResource($consultation->load('diagnoses')))->response()->setStatusCode(201);
    }

    public function show(Consultation $consultation): ConsultationResource
    {
        return new ConsultationResource($consultation->load('diagnoses'));
    }

    public function update(ConsultationRequest $request, Consultation $consultation): ConsultationResource
    {
        abort_if($consultation->status === 'terminee', 422, 'Une consultation clôturée ne peut plus être modifiée.');

        $consultation->update($request->validated());

        return new ConsultationResource($consultation->load('diagnoses'));
    }

    public function close(Consultation $consultation): ConsultationResource
    {
        abort_if($consultation->status === 'terminee', 422, 'Cette consultation est déjà clôturée.');

        $consultation->update(['status' => 'terminee', 'closed_at' => now()]);

        app(BillingService::class)->recordService($consultation);

        return new ConsultationResource($consultation->load('diagnoses'));
    }

    public function storeDiagnosis(ConsultationDiagnosisRequest $request, Consultation $consultation): JsonResponse
    {
        $icdCode = IcdCode::findOrFail($request->validated('icd_code_id'));

        $diagnosis = $consultation->diagnoses()->create(ConsultationDiagnosis::fromIcdCode(
            $icdCode,
            $request->validated('type'),
            $request->validated('status') ?? 'provisoire',
        ));

        return (new ConsultationDiagnosisResource($diagnosis))->response()->setStatusCode(201);
    }

    /**
     * Only the provisoire/confirme status can move — code/label/version
     * snapshots are immutable once recorded (see migration docblock). To
     * "change" a diagnosis, code a new one instead.
     */
    public function updateDiagnosis(Request $request, Consultation $consultation, ConsultationDiagnosis $diagnosis): ConsultationDiagnosisResource
    {
        abort_unless($diagnosis->consultation_id === $consultation->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(['provisoire', 'confirme'])],
        ]);

        $diagnosis->update($data);

        return new ConsultationDiagnosisResource($diagnosis);
    }
}
