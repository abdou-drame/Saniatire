<?php

namespace App\Domain\Notification\Channels;

use App\Domain\Notification\Contracts\NotificationChannel;
use App\Domain\Notification\Models\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Structure uniquement — aucun appel réseau réel (même logique que
 * SmsChannel).
 *
 * Point d'extension : WhatsApp Business Cloud API (Meta). Ajouter
 * WHATSAPP_BUSINESS_TOKEN / WHATSAPP_PHONE_NUMBER_ID dans .env +
 * config/services.php, puis remplacer le corps de send() par un appel
 * HTTP POST vers l'API Graph — le contrat NotificationChannel ne change
 * pas.
 */
class WhatsAppChannel implements NotificationChannel
{
    public function send(Notification $notification): void
    {
        Log::info('[WhatsApp simulé] Envoi à '.$notification->destinataire, [
            'type_evenement' => $notification->type_evenement,
            'contenu' => $notification->contenu_final,
        ]);
    }

    public function resolveDestinataire(Model $notifiable): ?string
    {
        return $notifiable->phone;
    }
}
