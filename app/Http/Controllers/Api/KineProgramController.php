<?php

namespace App\Http\Controllers\Api;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Kinesitherapie\Models\KineProgram;
use App\Domain\Kinesitherapie\Models\KineSession;
use App\Domain\Shared\Billing\BillingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\KineProgramRequest;
use App\Http\Requests\KineSessionRequest;
use App\Http\Resources\KineProgramResource;
use App\Http\Resources\KineSessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class KineProgramController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:kinesitherapie.view', only: ['index', 'show', 'stats']),
            new Middleware('permission:kinesitherapie.create', only: ['store', 'storeSession']),
            new Middleware('permission:kinesitherapie.update', only: ['update']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $programs = KineProgram::query()
            ->when($request->integer('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->orderByDesc('created_at')
            ->paginate();

        return KineProgramResource::collection($programs)->response();
    }

    public function store(KineProgramRequest $request): JsonResponse
    {
        $program = KineProgram::create($request->validated())->refresh();

        if ($program->consultation_id) {
            Consultation::whereKey($program->consultation_id)->update(['specialty_type' => KineProgram::specialtyType()]);
        }

        return (new KineProgramResource($program))->response()->setStatusCode(201);
    }

    public function show(KineProgram $kineProgram): KineProgramResource
    {
        return new KineProgramResource($kineProgram->load('sessions'));
    }

    public function update(KineProgramRequest $request, KineProgram $kineProgram): KineProgramResource
    {
        $kineProgram->update($request->validated());

        return new KineProgramResource($kineProgram);
    }

    public function storeSession(KineSessionRequest $request, KineProgram $kineProgram): JsonResponse
    {
        $session = $kineProgram->sessions()->create([
            ...$request->validated(),
            'practitioner_id' => $request->user()->id,
        ]);

        app(BillingService::class)->recordService($session->setRelation('program', $kineProgram));

        return (new KineSessionResource($session))->response()->setStatusCode(201);
    }

    /**
     * Basic session count over a period — the seed for a future kiné
     * dashboard, not a dashboard itself.
     */
    public function stats(Request $request): JsonResponse
    {
        $sessions = KineSession::query()
            ->whereHas('program')
            ->when($request->date('from'), fn ($q, $d) => $q->where('session_date', '>=', $d))
            ->when($request->date('to'), fn ($q, $d) => $q->where('session_date', '<=', $d))
            ->get();

        return response()->json(['data' => [
            'total_sessions' => $sessions->count(),
        ]]);
    }
}
