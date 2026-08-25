<?php

namespace App\Http\Controllers\Api;

use App\Domain\Notification\Models\NotificationTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\NotificationTemplateRequest;
use App\Http\Resources\NotificationTemplateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Gère uniquement les templates propres à la structure de l'appelant
 * (BelongsToTenant scope la requête et remplit structure_id à la création) —
 * les templates par défaut globaux (structure_id = null, cf.
 * NotificationTemplateSeeder) ne sont pas exposés ici, seulement en base.
 */
class NotificationTemplateController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:notifications.view', only: ['index', 'show']),
            new Middleware('permission:notifications.create', only: ['store']),
            new Middleware('permission:notifications.update', only: ['update']),
            new Middleware('permission:notifications.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = NotificationTemplate::query()
            ->when($request->filled('type_evenement'), fn ($q) => $q->where('type_evenement', $request->string('type_evenement')))
            ->when($request->filled('canal'), fn ($q) => $q->where('canal', $request->string('canal')));

        return NotificationTemplateResource::collection($query->orderBy('id')->paginate())->response();
    }

    public function store(NotificationTemplateRequest $request): JsonResponse
    {
        $template = NotificationTemplate::create($request->validated())->refresh();

        return (new NotificationTemplateResource($template))->response()->setStatusCode(201);
    }

    public function show(NotificationTemplate $notificationTemplate): NotificationTemplateResource
    {
        return new NotificationTemplateResource($notificationTemplate);
    }

    public function update(NotificationTemplateRequest $request, NotificationTemplate $notificationTemplate): NotificationTemplateResource
    {
        $notificationTemplate->update($request->validated());

        return new NotificationTemplateResource($notificationTemplate);
    }

    public function destroy(NotificationTemplate $notificationTemplate): JsonResponse
    {
        $notificationTemplate->delete();

        return response()->json(null, 204);
    }
}
