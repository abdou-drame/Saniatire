<?php

namespace App\Http\Controllers\Api;

use App\Domain\Hospitalisation\Models\Bed;
use App\Domain\Hospitalisation\Models\Hospitalization;
use App\Domain\Shared\Billing\BillingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\HospitalizationDailyNoteRequest;
use App\Http\Requests\HospitalizationDischargeRequest;
use App\Http\Requests\HospitalizationRequest;
use App\Http\Resources\HospitalizationDailyNoteResource;
use App\Http\Resources\HospitalizationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class HospitalizationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:hospitalisation.view', only: ['index', 'show', 'stats']),
            new Middleware('permission:hospitalisation.create', only: ['store']),
            new Middleware('permission:hospitalisation.update', only: ['discharge']),
            new Middleware('permission:hospitalisation.daily_note', only: ['storeDailyNote']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $hospitalizations = Hospitalization::query()
            ->with(['dailyNotes', 'patient:id,first_name,last_name,patient_number', 'bed', 'ward:id,name', 'attendingPhysician'])
            ->when($request->integer('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('admitted_at')
            ->paginate();

        return HospitalizationResource::collection($hospitalizations)->response();
    }

    public function stats(): JsonResponse
    {
        $totalBeds = Bed::count();
        $occupiedBeds = Bed::where('status', 'occupe')->count();

        return response()->json(['data' => [
            'lits_occupes' => $occupiedBeds,
            'lits_total' => $totalBeds,
            'taux_occupation' => $totalBeds > 0 ? round($occupiedBeds / $totalBeds * 100, 1) : 0.0,
            'admissions_du_jour' => Hospitalization::whereDate('admitted_at', now())->count(),
            'sorties_du_jour' => Hospitalization::whereDate('discharged_at', now())->count(),
        ]]);
    }

    /**
     * Blocking rule (§3/§7 cahier des charges) : un lit déjà occupé ne peut
     * pas être attribué à un second patient. lockForUpdate() sérialise les
     * admissions concurrentes sur le même lit pour fermer la fenêtre de
     * course entre la lecture du statut et l'écriture.
     */
    public function store(HospitalizationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $hospitalization = DB::transaction(function () use ($data) {
            $bed = Bed::whereKey($data['bed_id'])->lockForUpdate()->firstOrFail();

            abort_if($bed->status !== 'libre', 422, 'Ce lit est déjà occupé ou indisponible.');

            $hospitalization = Hospitalization::create([
                ...$data,
                'ward_id' => $bed->ward_id,
                'admitted_at' => now(),
            ])->refresh();

            $bed->update(['status' => 'occupe']);

            return $hospitalization;
        });

        return (new HospitalizationResource($hospitalization))->response()->setStatusCode(201);
    }

    public function show(Hospitalization $hospitalization): HospitalizationResource
    {
        return new HospitalizationResource($hospitalization->load([
            'dailyNotes',
            'patient:id,first_name,last_name,patient_number',
            'bed',
            'ward:id,name',
            'attendingPhysician',
        ]));
    }

    public function discharge(HospitalizationDischargeRequest $request, Hospitalization $hospitalization): HospitalizationResource
    {
        abort_if($hospitalization->status !== 'en_cours', 422, 'Cette hospitalisation est déjà clôturée.');

        DB::transaction(function () use ($request, $hospitalization) {
            $hospitalization->update([
                'status' => 'sorti',
                'discharged_at' => now(),
                'discharge_summary' => $request->validated('discharge_summary'),
            ]);

            $hospitalization->bed()->lockForUpdate()->first()?->update(['status' => 'libre']);
        });

        app(BillingService::class)->recordService($hospitalization->fresh());

        return new HospitalizationResource($hospitalization);
    }

    public function storeDailyNote(HospitalizationDailyNoteRequest $request, Hospitalization $hospitalization): JsonResponse
    {
        $note = $hospitalization->dailyNotes()->create([
            ...$request->validated(),
            'author_id' => $request->user()->id,
            'note_date' => $request->validated('note_date') ?? now()->toDateString(),
        ])->refresh();

        return (new HospitalizationDailyNoteResource($note))->response()->setStatusCode(201);
    }
}
