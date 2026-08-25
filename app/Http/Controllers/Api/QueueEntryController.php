<?php

namespace App\Http\Controllers\Api;

use App\Domain\Queue\Models\QueueEntry;
use App\Http\Controllers\Controller;
use App\Http\Requests\QueueEntryRequest;
use App\Http\Resources\QueueEntryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class QueueEntryController extends Controller implements HasMiddleware
{
    private const ACTIVE_STATUSES = ['en_attente', 'appele', 'en_consultation'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:queue.view', only: ['index', 'stats']),
            new Middleware('permission:queue.create', only: ['store']),
            new Middleware('permission:queue.update', only: ['updateStatus']),
        ];
    }

    /**
     * Live queue state, filterable by site/service. Defaults to only the
     * still-active entries (waiting/called/in consultation) so it reflects
     * "who's currently in the queue" rather than the full day's history.
     */
    public function index(Request $request): JsonResponse
    {
        $entries = QueueEntry::query()
            ->when(! $request->boolean('include_exited'), fn ($q) => $q->whereIn('status', self::ACTIVE_STATUSES))
            ->when($request->integer('site_id'), fn ($q, $id) => $q->where('site_id', $id))
            ->when($request->string('service')->isNotEmpty(), fn ($q) => $q->where('service', $request->string('service')))
            ->orderBy('arrived_at')
            ->get();

        return QueueEntryResource::collection($entries)->response();
    }

    public function store(QueueEntryRequest $request): JsonResponse
    {
        $entry = QueueEntry::create([
            ...$request->validated(),
            'arrived_at' => now(),
        ])->refresh();

        return (new QueueEntryResource($entry))->response()->setStatusCode(201);
    }

    /**
     * Every transition stamps its own timestamp column (see
     * QueueEntry::STATUS_TIMESTAMP_COLUMNS) so real wait/consultation
     * duration is computable afterwards.
     */
    public function updateStatus(Request $request, QueueEntry $queueEntry): QueueEntryResource
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['en_attente', 'appele', 'en_consultation', 'sorti'])],
        ]);

        $update = ['status' => $data['status']];

        if ($column = QueueEntry::STATUS_TIMESTAMP_COLUMNS[$data['status']] ?? null) {
            $update[$column] = now();
        }

        $queueEntry->update($update);

        return new QueueEntryResource($queueEntry);
    }

    /**
     * Average wait time (arrival -> called) over the given period, for the
     * caller's structure. Basis for future dashboards, not a full report.
     */
    public function stats(Request $request): JsonResponse
    {
        $from = ($request->date('from') ?? now())->startOfDay();
        $to = ($request->date('to') ?? now())->endOfDay();

        $entries = QueueEntry::query()
            ->whereNotNull('called_at')
            ->whereBetween('arrived_at', [$from, $to])
            ->when($request->integer('site_id'), fn ($q, $id) => $q->where('site_id', $id))
            ->get(['arrived_at', 'called_at']);

        $averageWaitMinutes = $entries->isEmpty()
            ? null
            : round($entries->avg(fn (QueueEntry $entry) => $entry->arrived_at->diffInMinutes($entry->called_at)), 1);

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'entries_count' => $entries->count(),
            'average_wait_minutes' => $averageWaitMinutes,
        ]);
    }
}
