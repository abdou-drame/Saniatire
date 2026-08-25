<?php

namespace App\Domain\Notification\Channels;

use App\Domain\Notification\Contracts\NotificationChannel;
use App\Domain\Notification\Models\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Structure uniquement — aucun appel réseau réel (même logique que
 * SmsChannel/WhatsAppChannel).
 *
 * Point d'extension : Firebase Cloud Messaging. Ajouter
 * FIREBASE_PROJECT_ID / FIREBASE_CREDENTIALS dans .env +
 * config/services.php, puis remplacer le corps de send() par un appel au
 * SDK FCM — le contrat NotificationChannel ne change pas. Nécessite aussi,
 * côté client, la collecte d'un jeton d'appareil par notifiable (absente
 * du socle actuel, hors périmètre de cette étape).
 */
class PushChannel implements NotificationChannel
{
    public function send(Notification $notification): void
    {
        Log::info('[Push simulé] Envoi à '.$notification->destinataire, [
            'type_evenement' => $notification->type_evenement,
            'contenu' => $notification->contenu_final,
        ]);
    }

    /**
     * Le socle actuel n'a pas de champ "jeton d'appareil" par notifiable
     * (aucune collecte côté client) — on retombe sur le téléphone comme
     * identifiant simulé. Une vraie intégration FCM remplacerait ceci par
     * $notifiable->device_token une fois cette colonne ajoutée, sans
     * changement au dispatcher.
     */
    public function resolveDestinataire(Model $notifiable): ?string
    {
        return $notifiable->phone;
    }
}
