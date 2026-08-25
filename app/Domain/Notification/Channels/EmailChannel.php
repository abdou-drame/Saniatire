<?php

namespace App\Domain\Notification\Channels;

use App\Domain\Notification\Contracts\NotificationChannel;
use App\Domain\Notification\Mail\GenericNotificationMail;
use App\Domain\Notification\Models\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

/**
 * Intégration réelle : passe par la façade Mail de Laravel. En développement
 * MAIL_MAILER=log (voir .env.example) écrit l'email complet dans
 * storage/logs/laravel.log au lieu d'un vrai envoi SMTP — pour basculer
 * vers un vrai service (Mailtrap, SES, Postmark...), il suffit de changer
 * les variables MAIL_* en environnement, aucun changement de code.
 */
class EmailChannel implements NotificationChannel
{
    public function send(Notification $notification): void
    {
        Mail::to($notification->destinataire)->send(new GenericNotificationMail($notification));
    }

    public function resolveDestinataire(Model $notifiable): ?string
    {
        return $notifiable->email;
    }
}
