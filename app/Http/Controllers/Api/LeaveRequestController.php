<?php

namespace App\Http\Controllers\Api;

use App\Domain\Notification\Events\CongeValide;
use App\Domain\Rh\Models\LeaveRequest;
use App\Domain\Shared\Scheduling\PractitionerPresenceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\LeaveRequestRequest;
use App\Http\Resources\LeaveRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class LeaveRequestController extends Controller implements HasMiddleware
{
    /**
     * store/index/show carry no permission gate: every user may request
     * their own leave and consult their own requests (§5) — enforced
     * below rather than through the grid. Only validate/refuse (deciding
     * on someone else's request) require conges.validate.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:conges.validate', only: ['validateRequest', 'refuse']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $canViewOthers = $request->user()->can('conges.view');
        $requestedUserId = $request->integer('user_id');

        abort_if($requestedUserId && $requestedUserId !== $request->user()->id && ! $canViewOthers, 403);

        $query = LeaveRequest::query();

        if ($requestedUserId) {
            $query->where('user_id', $requestedUserId);
        } elseif (! $canViewOthers) {
            $query->where('user_id', $request->user()->id);
        }

        $query->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')));

        return LeaveRequestResource::collection($query->orderByDesc('id')->paginate())->response();
    }

    /**
     * user_id is always forced to the caller's own id — a leave request is
     * always for oneself (§5 "chaque utilisateur crée ses propres
     * demandes, pas celles des autres"), so no permission is needed here:
     * self-service leave requests aren't a privileged action, only
     * validating one is.
     */
    public function store(LeaveRequestRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['statut'] = 'demande';

        $leaveRequest = LeaveRequest::create($data)->refresh();

        return (new LeaveRequestResource($leaveRequest))->response()->setStatusCode(201);
    }

    public function show(Request $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        abort_if($leaveRequest->user_id !== $request->user()->id && ! $request->user()->can('conges.view'), 403);

        return new LeaveRequestResource($leaveRequest);
    }

    /**
     * Validation non bloquante en cas de chevauchement avec un
     * work_schedule déjà planifié : la réponse inclut un tableau
     * "warnings" plutôt que de refuser la validation (voir
     * PractitionerPresenceService::scheduleOverlapWarnings).
     */
    public function validateRequest(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        abort_if($leaveRequest->statut !== 'demande', 422, 'Cette demande a déjà été traitée.');

        $this->assertCanDecide($request, $leaveRequest);

        $leaveRequest->update(['statut' => 'valide', 'validated_by' => $request->user()->id]);

        CongeValide::dispatch($leaveRequest);

        $warnings = app(PractitionerPresenceService::class)->scheduleOverlapWarnings($leaveRequest->structure_id, $leaveRequest);

        return response()->json([
            'data' => (new LeaveRequestResource($leaveRequest))->resolve($request),
            'warnings' => $warnings,
        ]);
    }

    public function refuse(Request $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        abort_if($leaveRequest->statut !== 'demande', 422, 'Cette demande a déjà été traitée.');

        $this->assertCanDecide($request, $leaveRequest);

        $leaveRequest->update(['statut' => 'refuse', 'validated_by' => $request->user()->id]);

        return new LeaveRequestResource($leaveRequest);
    }

    /**
     * Portée équipe (§5) : sans conges.validate_all (RH/administrateur/
     * direction), un validateur (ex. manager) ne peut décider que des
     * demandes d'utilisateurs partageant au moins un site avec lui — pas
     * de table de hiérarchie dédiée, l'équipe est définie via user_site,
     * la relation de rattachement site déjà existante.
     */
    private function assertCanDecide(Request $request, LeaveRequest $leaveRequest): void
    {
        if ($request->user()->can('conges.validate_all')) {
            return;
        }

        $validatorSiteIds = $request->user()->sites()->pluck('sites.id');
        $requesterSiteIds = $leaveRequest->user->sites()->pluck('sites.id');

        abort_if(
            $validatorSiteIds->intersect($requesterSiteIds)->isEmpty(),
            403,
            'Vous ne pouvez valider que les congés des utilisateurs de votre équipe.'
        );
    }
}
