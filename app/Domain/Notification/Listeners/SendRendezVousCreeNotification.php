<?php

namespace App\Domain\Notification\Listeners;

use App\Domain\Appointment\Events\RendezVousCree;
use App\Domain\Notification\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendRendezVousCreeNotification implements ShouldQueue
{
    public function __construct(private NotificationDispatcher $dispatcher)
    {
    }

    public function handle(RendezVousCree $event): void
    {
        $appointment = $event->appointment;
        $patient = $appointment->patient;

        if (! $patient) {
            return;
        }

        $this->dispatcher->send($patient, 'rdv_cree', [
            'patient_nom' => trim($patient->first_name.' '.$patient->last_name),
            'date_rdv' => $appointment->starts_at->format('d/m/Y à H:i'),
            'praticien_nom' => $appointment->practitioner
                ? trim($appointment->practitioner->first_name.' '.$appointment->practitioner->last_name)
                : '',
        ]);
    }
}
