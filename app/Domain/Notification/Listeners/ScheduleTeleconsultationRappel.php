<?php

namespace App\Domain\Notification\Listeners;

use App\Domain\Notification\NotificationDispatcher;
use App\Domain\Teleconsultation\Events\TeleconsultationPlanifiee;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Même patron que ScheduleRendezVousRappel (étape 7a) : la ligne
 * "notifications" programmée EST l'objet planifié, consommée plus tard par
 * notifications:process-due.
 */
class ScheduleTeleconsultationRappel implements ShouldQueue
{
    public function __construct(private NotificationDispatcher $dispatcher)
    {
    }

    public function handle(TeleconsultationPlanifiee $event): void
    {
        $teleconsultation = $event->teleconsultation;
        $appointment = $teleconsultation->appointment;
        $patient = $teleconsultation->patient;

        if (! $appointment || ! $patient) {
            return;
        }

        $delaiHeures = config('notifications.rappel_teleconsultation_delai_heures');
        $scheduledFor = $appointment->starts_at->clone()->subHours($delaiHeures);

        if ($scheduledFor->isPast()) {
            return;
        }

        $this->dispatcher->send($patient, 'teleconsultation_rappel', [
            'patient_nom' => trim($patient->first_name.' '.$patient->last_name),
            'date_rdv' => $appointment->starts_at->format('d/m/Y à H:i'),
        ], $scheduledFor);
    }
}
