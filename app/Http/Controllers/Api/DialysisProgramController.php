<?php

namespace App\Http\Controllers\Api;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Dialyse\Models\DialysisProgram;
use App\Domain\Dialyse\Models\DialysisSession;
use App\Domain\Shared\Billing\BillingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\DialysisProgramRequest;
use App\Http\Requests\DialysisSessionRequest;
use App\Http\Requests\DialysisSessionVitalRequest;
use App\Http\Resources\DialysisProgramResource;
use App\Http\Resources\DialysisSessionResource;
use App\Http\Resources\DialysisSessionVitalResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DialysisProgramController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:dialyse.view', only: ['index', 'show', 'stats']),
            // Opening/adjusting a program is a prescription-level decision
            // reserved to the néphrologue (dialyse.create/.update). Recording
            // a session or its vitals is the infirmier's routine task, so it
            // also accepts the narrower dialyse.record_session permission.
            new Middleware('permission:dialyse.create', only: ['store']),
            new Middleware('permission:dialyse.update', only: ['update']),
            new Middleware('permission:dialyse.create|dialyse.record_session', only: ['storeSession', 'storeSessionVital']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $programs = DialysisProgram::query()
            ->when($request->integer('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->orderByDesc('created_at')
            ->paginate();

        return DialysisProgramResource::collection($programs)->response();
    }

    public function store(DialysisProgramRequest $request): JsonResponse
    {
        $program = DialysisProgram::create($request->validated())->refresh();

        if ($program->consultation_id) {
            Consultation::whereKey($program->consultation_id)->update(['specialty_type' => DialysisProgram::specialtyType()]);
        }

        return (new DialysisProgramResource($program))->response()->setStatusCode(201);
    }

    public function show(DialysisProgram $dialysisProgram): DialysisProgramResource
    {
        return new DialysisProgramResource($dialysisProgram->load('sessions.vitals'));
    }

    public function update(DialysisProgramRequest $request, DialysisProgram $dialysisProgram): DialysisProgramResource
    {
        $dialysisProgram->update($request->validated());

        return new DialysisProgramResource($dialysisProgram);
    }

    public function storeSession(DialysisSessionRequest $request, DialysisProgram $dialysisProgram): JsonResponse
    {
        $session = $dialysisProgram->sessions()->create([
            ...$request->validated(),
            'practitioner_id' => $request->user()->id,
            'status' => 'terminee',
        ]);

        app(BillingService::class)->recordService($session->setRelation('program', $dialysisProgram));

        return (new DialysisSessionResource($session))->response()->setStatusCode(201);
    }

    public function storeSessionVital(DialysisSessionVitalRequest $request, DialysisSession $session): JsonResponse
    {
        // DialysisSession carries no structure_id of its own — route
        // binding alone won't reject another structure's id, so tenant
        // scoping must be checked explicitly through its parent program.
        abort_unless($session->program()->exists(), 404);

        $vital = $session->vitals()->create($request->validated());

        return (new DialysisSessionVitalResource($vital))->response()->setStatusCode(201);
    }

    /**
     * Basic session count over a period — the seed for a future dialysis
     * dashboard, not a dashboard itself.
     */
    public function stats(Request $request): JsonResponse
    {
        $sessions = DialysisSession::query()
            ->whereHas('program')
            ->when($request->date('from'), fn ($q, $d) => $q->where('session_date', '>=', $d))
            ->when($request->date('to'), fn ($q, $d) => $q->where('session_date', '<=', $d))
            ->get();

        return response()->json(['data' => [
            'total_sessions' => $sessions->count(),
        ]]);
    }
}
