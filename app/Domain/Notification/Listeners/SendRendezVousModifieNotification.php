<?php

namespace App\Domain\Notification\Listeners;

use App\Domain\Appointment\Events\RendezVousModifie;
use App\Domain\Notification\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendRendezVousModifieNotification implements ShouldQueue
{
    public function __construct(private NotificationDispatcher $dispatcher)
    {
    }

    public function handle(RendezVousModifie $event): void
    {
        $appointment = $event->appointment;
        $patient = $appointment->patient;

        if (! $patient) {
            return;
        }

        $this->dispatcher->send($patient, 'rdv_modifie', [
            'patient_nom' => trim($patient->first_name.' '.$patient->last_name),
            'date_rdv' => $appointment->starts_at->format('d/m/Y à H:i'),
            'praticien_nom' => $appointment->practitioner
                ? trim($appointment->practitioner->first_name.' '.$appointment->practitioner->last_name)
                : '',
        ]);
    }
}
