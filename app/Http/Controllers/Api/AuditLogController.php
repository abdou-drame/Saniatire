<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Étape 9 §3 : consultation du journal d'audit (lecture seule — voir
 * AppServiceProvider::boot() pour les gardes updating/deleting qui rendent
 * ce journal append-only, y compris pour un administrateur). Réservé au
 * rôle `conformite` (et administrateur, via son accès `*`).
 */
class AuditLogController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:audit.view', only: ['index']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'action' => ['nullable', 'string'],
            'table' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        // Le modèle Activity vient du package et n'a pas BelongsToTenant :
        // l'isolation multi-tenant s'appuie sur structure_id renseigné à
        // l'écriture (AppServiceProvider::boot()), pas sur un scope global.
        $query = Activity::query()->where('structure_id', $request->user()->structure_id);

        if (! empty($data['user_id'])) {
            $query->where('causer_id', $data['user_id'])->where('causer_type', \App\Domain\User\Models\User::class);
        }

        if (! empty($data['action'])) {
            $query->where(function ($q) use ($data) {
                $q->where('event', $data['action'])
                    ->orWhere('description', 'like', "%{$data['action']}%")
                    ->orWhere('log_name', $data['action']);
            });
        }

        if (! empty($data['table'])) {
            $query->where('subject_type', 'like', "%{$data['table']}%");
        }

        if (! empty($data['from'])) {
            $query->where('created_at', '>=', Carbon::parse($data['from'])->startOfDay());
        }

        if (! empty($data['to'])) {
            $query->where('created_at', '<=', Carbon::parse($data['to'])->endOfDay());
        }

        $activities = $query->orderByDesc('created_at')
            ->paginate($data['per_page'] ?? 50)
            ->through(fn (Activity $activity) => [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'description' => $activity->description,
                'event' => $activity->event,
                'subject_type' => $activity->subject_type,
                'subject_id' => $activity->subject_id,
                'causer_id' => $activity->causer_id,
                'ip_address' => $activity->ip_address,
                'properties' => $activity->properties,
                'created_at' => $activity->created_at,
            ]);

        return response()->json($activities);
    }
}
