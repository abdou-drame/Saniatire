<?php

namespace App\Domain\Notification\Listeners;

use App\Domain\Appointment\Events\RendezVousAnnule;
use App\Domain\Notification\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendRendezVousAnnuleNotification implements ShouldQueue
{
    public function __construct(private NotificationDispatcher $dispatcher)
    {
    }

    public function handle(RendezVousAnnule $event): void
    {
        $appointment = $event->appointment;
        $patient = $appointment->patient;

        if (! $patient) {
            return;
        }

        $this->dispatcher->send($patient, 'rdv_annule', [
            'patient_nom' => trim($patient->first_name.' '.$patient->last_name),
            'date_rdv' => $appointment->starts_at->format('d/m/Y à H:i'),
        ]);
    }
}
