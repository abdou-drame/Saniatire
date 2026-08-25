<?php

namespace App\Domain\Notification\Listeners;

use App\Domain\Notification\Events\CongeValide;
use App\Domain\Notification\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendCongeValideNotification implements ShouldQueue
{
    public function __construct(private NotificationDispatcher $dispatcher)
    {
    }

    public function handle(CongeValide $event): void
    {
        $leaveRequest = $event->leaveRequest;
        $user = $leaveRequest->user;

        if (! $user) {
            return;
        }

        $this->dispatcher->send($user, 'conge_valide', [
            'user_nom' => trim($user->first_name.' '.$user->last_name),
            'date_debut' => $leaveRequest->date_debut->format('d/m/Y'),
            'date_fin' => $leaveRequest->date_fin->format('d/m/Y'),
        ]);
    }
}
