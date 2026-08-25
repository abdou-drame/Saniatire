<?php

namespace App\Http\Controllers\Api;

use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\Patient\Models\Patient;
use App\Http\Controllers\Controller;
use App\Http\Requests\NotificationPreferenceRequest;
use App\Http\Resources\NotificationPreferenceResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Deux façons d'atteindre les mêmes préférences : chaque utilisateur gère
 * les siennes sans permission dédiée (show/update, toujours auth()->user()),
 * tandis que le personnel gère celles d'un patient via la permission
 * patients.update déjà existante — un patient n'a pas encore de compte
 * propre (cf. étape 7b), donc pas de nouvelle permission nécessaire ici.
 */
class NotificationPreferenceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:patients.update', only: ['showForPatient', 'updateForPatient']),
        ];
    }

    public function show(Request $request): NotificationPreferenceResource
    {
        return $this->resourceFor($request->user());
    }

    public function update(NotificationPreferenceRequest $request): NotificationPreferenceResource
    {
        return $this->upsertFor($request->user(), $request->validated());
    }

    public function showForPatient(Patient $patient): NotificationPreferenceResource
    {
        return $this->resourceFor($patient);
    }

    public function updateForPatient(NotificationPreferenceRequest $request, Patient $patient): NotificationPreferenceResource
    {
        return $this->upsertFor($patient, $request->validated());
    }

    /**
     * Absence de ligne = préférences par défaut (config), pas une 404 : le
     * dispatcher applique la même règle (cf. NotificationDispatcher::
     * resolveChannels), donc ce que l'appelant voit ici correspond
     * exactement à ce qui sera utilisé à l'envoi.
     */
    private function resourceFor(Model $notifiable): NotificationPreferenceResource
    {
        $preference = NotificationPreference::query()
            ->where('notifiable_type', $notifiable::class)
            ->where('notifiable_id', $notifiable->getKey())
            ->first();

        if (! $preference) {
            $preference = new NotificationPreference([
                'notifiable_type' => $notifiable::class,
                'notifiable_id' => $notifiable->getKey(),
                'canaux' => config('notifications.default_channels'),
            ]);
        }

        return new NotificationPreferenceResource($preference);
    }

    private function upsertFor(Model $notifiable, array $data): NotificationPreferenceResource
    {
        $preference = NotificationPreference::updateOrCreate(
            ['notifiable_type' => $notifiable::class, 'notifiable_id' => $notifiable->getKey()],
            ['canaux' => $data['canaux'], 'structure_id' => $notifiable->structure_id]
        );

        return new NotificationPreferenceResource($preference);
    }
}
