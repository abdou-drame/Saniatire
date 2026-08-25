<?php

namespace App\Domain\Notification\Listeners;

use App\Domain\Laboratoire\Events\ResultatLaboratoireDisponible;
use App\Domain\Notification\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendResultatDisponibleNotification implements ShouldQueue
{
    public function __construct(private NotificationDispatcher $dispatcher)
    {
    }

    public function handle(ResultatLaboratoireDisponible $event): void
    {
        $patient = $event->labResult->orderItem?->labOrder?->patient;

        if (! $patient) {
            return;
        }

        $this->dispatcher->send($patient, 'resultat_disponible', [
            'patient_nom' => trim($patient->first_name.' '.$patient->last_name),
        ]);
    }
}
