<?php

namespace App\Domain\Notification\Listeners;

use App\Domain\Imagerie\Events\ResultatImagerieTransmis;
use App\Domain\Laboratoire\Events\ResultatLaboratoireTransmis;
use App\Domain\Notification\NotificationDispatcher;
use App\Domain\Prescripteur\Models\ExternalPrescriber;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Un requester interne (User) n'a pas de portail/notification e-mail dans ce
 * périmètre — seul un ExternalPrescriber déclenche
 * `resultat_disponible_prescripteur`, jamais un membre du personnel.
 */
class SendResultatDisponiblePrescripteurNotification implements ShouldQueue
{
    public function __construct(private NotificationDispatcher $dispatcher)
    {
    }

    public function handleLaboratoire(ResultatLaboratoireTransmis $event): void
    {
        $requester = $event->labResult->orderItem?->labOrder?->requester;

        $this->notify($requester);
    }

    public function handleImagerie(ResultatImagerieTransmis $event): void
    {
        $requester = $event->imagingStudy->imagingOrder?->requester;

        $this->notify($requester);
    }

    private function notify(?object $requester): void
    {
        if (! $requester instanceof ExternalPrescriber) {
            return;
        }

        $this->dispatcher->send($requester, 'resultat_disponible_prescripteur', [
            'prescripteur_nom' => $requester->nom,
        ]);
    }
}
