<?php

namespace App\Domain\Shared\Auth\Listeners;

use App\Domain\Notification\NotificationDispatcher;
use App\Domain\Patient\Models\Patient;
use App\Domain\Shared\Auth\Events\PortailActivationDemandee;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPortailActivationNotification implements ShouldQueue
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(PortailActivationDemandee $event): void
    {
        $activatable = $event->activatable;

        $portail = $activatable instanceof Patient ? 'portail-patient' : 'portail-prescripteur';
        $nom = $activatable instanceof Patient
            ? trim("{$activatable->first_name} {$activatable->last_name}")
            : $activatable->nom;

        $lien = config('app.frontend_url', config('app.url'))."/{$portail}/activer?token={$event->token}";

        $this->dispatcher->send($activatable, 'patient_portal_activation', [
            'patient_nom' => $nom,
            'lien_activation' => $lien,
        ]);
    }
}
