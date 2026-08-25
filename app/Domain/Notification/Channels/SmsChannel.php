<?php

namespace App\Domain\Notification\Channels;

use App\Domain\Notification\Contracts\NotificationChannel;
use App\Domain\Notification\Models\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Structure uniquement — aucun appel réseau réel, même logique que les
 * autres intégrations externes du projet non encore branchées (cf.
 * database/seeders/README-PACS.md pour le PACS, config('services') pour
 * les clés tierces déjà prévues mais vides).
 *
 * Point d'extension pour une vraie intégration : Twilio (ou tout
 * fournisseur SMS compatible). Ajouter TWILIO_SID / TWILIO_AUTH_TOKEN /
 * TWILIO_FROM dans .env + config/services.php, puis remplacer le corps de
 * send() par un appel HTTP au SDK Twilio — le contrat NotificationChannel
 * ne change pas, donc aucun autre fichier du projet n'a besoin d'être
 * modifié pour ce branchement.
 */
class SmsChannel implements NotificationChannel
{
    public function send(Notification $notification): void
    {
        Log::info('[SMS simulé] Envoi à '.$notification->destinataire, [
            'type_evenement' => $notification->type_evenement,
            'contenu' => $notification->contenu_final,
        ]);
    }

    public function resolveDestinataire(Model $notifiable): ?string
    {
        return $notifiable->phone;
    }
}
