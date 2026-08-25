<?php

namespace App\Domain\Notification\Listeners;

use App\Domain\Notification\NotificationDispatcher;
use App\Domain\Referral\Events\ReferencementAccepte;
use App\Domain\Referral\Events\ReferencementRefuse;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendReferencementStatutNotification implements ShouldQueue
{
    public function __construct(private NotificationDispatcher $dispatcher)
    {
    }

    public function handleAccepte(ReferencementAccepte $event): void
    {
        $praticien = $event->referral->praticienReferent;

        if (! $praticien) {
            return;
        }

        $this->dispatcher->send($praticien, 'referencement_accepte', [
            'praticien_nom' => $praticien->fullName(),
        ]);
    }

    public function handleRefuse(ReferencementRefuse $event): void
    {
        $praticien = $event->referral->praticienReferent;

        if (! $praticien) {
            return;
        }

        $this->dispatcher->send($praticien, 'referencement_refuse', [
            'praticien_nom' => $praticien->fullName(),
        ]);
    }
}
