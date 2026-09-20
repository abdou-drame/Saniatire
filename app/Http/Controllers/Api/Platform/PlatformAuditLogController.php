<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Consultation du journal d'audit propre à l'administration plateforme —
 * distinct de AuditLogController (qui filtre par structure_id de
 * l'utilisateur courant et n'aurait donc jamais accès à ces entrées :
 * les actions plateforme n'appartiennent à aucune structure agissante).
 * Filtré sur le log_name dédié 'administration_plateforme' uniquement.
 */
class PlatformAuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'structure_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = Activity::query()->where('log_name', 'administration_plateforme');

        if (! empty($data['structure_id'])) {
            $query->where('structure_id', $data['structure_id']);
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
                'description' => $activity->description,
                'structure_id' => $activity->structure_id,
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
