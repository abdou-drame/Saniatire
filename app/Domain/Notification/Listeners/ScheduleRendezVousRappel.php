<?php

namespace App\Domain\Notification\Listeners;

use App\Domain\Appointment\Events\RendezVousCree;
use App\Domain\Notification\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Programme le rappel de RDV au moment de la création : la ligne
 * "notifications" créée avec scheduled_for = starts_at - délai EST l'objet
 * planifié (pas de Job séparé avec ->delay()), consommée plus tard par la
 * commande notifications:process-due. Limite documentée : si le RDV est
 * ensuite déplacé (RendezVousModifie), ce rappel déjà programmé n'est pas
 * recalculé — hors périmètre de cette étape.
 */
class ScheduleRendezVousRappel implements ShouldQueue
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

        $delaiHeures = config('notifications.rappel_rdv_delai_heures');
        $scheduledFor = $appointment->starts_at->clone()->subHours($delaiHeures);

        if ($scheduledFor->isPast()) {
            return;
        }

        $this->dispatcher->send($patient, 'rdv_rappel', [
            'patient_nom' => trim($patient->first_name.' '.$patient->last_name),
            'date_rdv' => $appointment->starts_at->format('d/m/Y à H:i'),
            'praticien_nom' => $appointment->practitioner
                ? trim($appointment->practitioner->first_name.' '.$appointment->practitioner->last_name)
                : '',
        ], $scheduledFor);
    }
}
