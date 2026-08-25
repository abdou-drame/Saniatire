<?php

namespace App\Domain\Notification\Mail;

use App\Domain\Notification\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class GenericNotificationMail extends Mailable
{
    use Queueable;

    public function __construct(public Notification $notification)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notification->sujet_final ?? 'Notification',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: nl2br(e($this->notification->contenu_final)),
        );
    }
}
