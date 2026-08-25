<?php

namespace App\Http\Controllers\Api;

use App\Domain\Qualite\Models\Complaint;
use App\Http\Controllers\Controller;
use App\Http\Resources\ComplaintResource;
use App\Http\Resources\ComplaintResponseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Workflow strictement ordonné (décision 1 de l'étape 8, pas de saut
 * d'étape) : ouverte -> en_cours (assign) -> resolue (resolve) -> close
 * (close). Chaque transition hors-ordre est rejetée en 422. La portée
 * "own scope" (respond/resolve/close) mirrors LeaveRequestController::
 * assertCanDecide, mais bornée à l'assignation directe (gestionnaire_id)
 * plutôt qu'à un site partagé.
 */
class ComplaintController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:reclamations.create', only: ['store']),
            new Middleware('permission:reclamations.update', only: ['assign']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $canManageAll = $request->user()->can('reclamations.manage_all');

        $query = Complaint::query()
            ->when(! $canManageAll, fn ($q) => $q->where('gestionnaire_id', $request->user()->id))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')));

        return ComplaintResource::collection($query->orderByDesc('id')->paginate())->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'motif' => ['required', 'string'],
            'description' => ['required', 'string'],
            'service_concerne' => ['nullable', 'string'],
        ]);

        $data['statut'] = 'ouverte';

        $complaint = Complaint::create($data)->refresh();

        return (new ComplaintResource($complaint))->response()->setStatusCode(201);
    }

    public function show(Request $request, Complaint $complaint): ComplaintResource
    {
        $this->assertCanManage($request, $complaint);

        return new ComplaintResource($complaint->load('responses'));
    }

    public function assign(Request $request, Complaint $complaint): ComplaintResource
    {
        abort_if($complaint->statut !== 'ouverte', 422, 'Seule une réclamation ouverte peut être assignée.');

        $data = $request->validate([
            'gestionnaire_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $complaint->update(['gestionnaire_id' => $data['gestionnaire_id'], 'statut' => 'en_cours']);

        return new ComplaintResource($complaint);
    }

    public function respond(Request $request, Complaint $complaint): JsonResponse
    {
        $this->assertCanManage($request, $complaint);

        $data = $request->validate([
            'message' => ['required', 'string'],
        ]);

        $response = $complaint->responses()->create([
            'auteur_id' => $request->user()->id,
            'message' => $data['message'],
        ]);

        return (new ComplaintResponseResource($response))->response()->setStatusCode(201);
    }

    public function resolve(Request $request, Complaint $complaint): ComplaintResource
    {
        $this->assertCanManage($request, $complaint);

        abort_if($complaint->statut !== 'en_cours', 422, 'Seule une réclamation en cours peut être résolue.');

        $complaint->update(['statut' => 'resolue', 'resolved_at' => now()]);

        return new ComplaintResource($complaint);
    }

    public function close(Request $request, Complaint $complaint): ComplaintResource
    {
        $this->assertCanManage($request, $complaint);

        abort_if($complaint->statut !== 'resolue', 422, 'Seule une réclamation résolue peut être clôturée.');

        $complaint->update(['statut' => 'close', 'closed_at' => now()]);

        return new ComplaintResource($complaint);
    }

    private function assertCanManage(Request $request, Complaint $complaint): void
    {
        if ($request->user()->can('reclamations.manage_all')) {
            return;
        }

        abort_if($complaint->gestionnaire_id !== $request->user()->id, 403, 'Vous ne pouvez agir que sur les réclamations qui vous sont assignées.');
    }
}
