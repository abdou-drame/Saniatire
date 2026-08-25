<?php

namespace App\Domain\Notification\Contracts;

use App\Domain\Notification\Models\Notification;

use Illuminate\Database\Eloquent\Model;

/**
 * Contrat unique implémenté par chaque canal (email, sms, whatsapp, push).
 * Le reste de l'application ne connaît que NotificationDispatcher — jamais
 * une classe de canal directement. Pour ajouter un nouveau canal : créer
 * une classe qui implémente cette interface et l'enregistrer dans
 * config('notifications.channels'), sans toucher au dispatcher ni aux
 * autres canaux.
 */
interface NotificationChannel
{
    /**
     * Doit lever une exception en cas d'échec — NotificationDispatcher
     * l'attrape et marque la notification "echouee" avec le message.
     */
    public function send(Notification $notification): void;

    /**
     * Extrait l'adresse/identifiant destinataire depuis le notifiable
     * (email, téléphone, jeton d'appareil...) — chaque canal sait seul de
     * quel attribut il a besoin, le dispatcher ne connaît aucun canal en
     * particulier. Retourne null si le notifiable n'a pas ce qu'il faut
     * pour ce canal (la notification est alors ignorée).
     */
    public function resolveDestinataire(Model $notifiable): ?string;
}
