<?php

namespace App\Http\Controllers\Api;

use App\Domain\Rh\Models\WorkSchedule;
use App\Domain\Shared\Scheduling\PractitionerPresenceService;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkScheduleRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Http\Resources\WorkScheduleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;

class WorkScheduleController extends Controller implements HasMiddleware
{
    /**
     * index/show/planning have no permission gate: every user may consult
     * their own planning (§5), enforced below by forcing the query to
     * auth()->id() unless the caller holds rh.view. Only management
     * actions require rh.*.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:rh.create', only: ['store']),
            new Middleware('permission:rh.update', only: ['update']),
            new Middleware('permission:rh.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $canViewOthers = $request->user()->can('rh.view');
        $requestedUserId = $request->integer('user_id');

        abort_if($requestedUserId && $requestedUserId !== $request->user()->id && ! $canViewOthers, 403);

        $query = WorkSchedule::query();

        if ($requestedUserId) {
            $query->where('user_id', $requestedUserId);
        } elseif (! $canViewOthers) {
            $query->where('user_id', $request->user()->id);
        }

        $query->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->integer('site_id'), fn ($q, $id) => $q->where('site_id', $id));

        return WorkScheduleResource::collection($query->orderBy('id')->paginate())->response();
    }

    public function store(WorkScheduleRequest $request): JsonResponse
    {
        $data = $this->normalizeTimes($request->validated());

        $schedule = WorkSchedule::create($data)->refresh();

        return (new WorkScheduleResource($schedule))->response()->setStatusCode(201);
    }

    public function show(Request $request, WorkSchedule $workSchedule): WorkScheduleResource
    {
        abort_if($workSchedule->user_id !== $request->user()->id && ! $request->user()->can('rh.view'), 403);

        return new WorkScheduleResource($workSchedule);
    }

    public function update(WorkScheduleRequest $request, WorkSchedule $workSchedule): WorkScheduleResource
    {
        $workSchedule->update($this->normalizeTimes($request->validated()));

        return new WorkScheduleResource($workSchedule);
    }

    public function destroy(WorkSchedule $workSchedule): JsonResponse
    {
        $workSchedule->delete();

        return response()->json(null, 204);
    }

    /**
     * Planning combiné (§2) : horaires normaux + gardes/astreintes sur la
     * période, congés validés soustraits. Même auto-restriction que index.
     */
    public function planning(Request $request, User $user): JsonResponse
    {
        abort_if($user->id !== $request->user()->id && ! $request->user()->can('rh.view'), 403);

        $from = ($request->date('from') ?? now())->startOfDay();
        $to = ($request->date('to') ?? now()->addWeek())->endOfDay();

        $planning = app(PractitionerPresenceService::class)->planningFor($user->structure_id, $user->id, $from, $to);

        return response()->json([
            'data' => [
                'horaires' => $planning['horaires'],
                'jours_conges' => $planning['jours_conges'],
                'conges' => LeaveRequestResource::collection($planning['conges'])->resolve(),
            ],
        ]);
    }

    /**
     * Client sends "H:i" (e.g. "08:00"); normalized to "H:i:s" before
     * storage so string time comparisons in PractitionerPresenceService
     * stay consistent regardless of the underlying DB driver's own
     * formatting of the `time` column.
     */
    private function normalizeTimes(array $data): array
    {
        foreach (['heure_debut', 'heure_fin'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = Carbon::createFromFormat('H:i', $data[$field])->format('H:i:s');
            }
        }

        return $data;
    }
}
