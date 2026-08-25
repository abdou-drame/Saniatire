<?php

use App\Domain\Notification\Channels\EmailChannel;
use App\Domain\Notification\Channels\PushChannel;
use App\Domain\Notification\Channels\SmsChannel;
use App\Domain\Notification\Channels\WhatsAppChannel;

return [

    /*
    |--------------------------------------------------------------------------
    | Canaux disponibles
    |--------------------------------------------------------------------------
    |
    | Seul fichier à modifier pour ajouter ou retirer un canal : chaque
    | classe doit implémenter App\Domain\Notification\Contracts\
    | NotificationChannel. NotificationDispatcher résout la classe via le
    | conteneur, aucun autre fichier n'a besoin d'être touché.
    |
    */
    'channels' => [
        'email' => EmailChannel::class,
        'sms' => SmsChannel::class,
        'whatsapp' => WhatsAppChannel::class,
        'push' => PushChannel::class,
    ],

    // Canaux utilisés quand un notifiable n'a défini aucune préférence.
    'default_channels' => ['email'],

    // Délai avant un rendez-vous auquel le rappel automatique est programmé.
    'rappel_rdv_delai_heures' => 24,

    // Délai avant une téléconsultation auquel le rappel automatique est programmé.
    'rappel_teleconsultation_delai_heures' => 24,

    // Nombre de jours avant date_echeance à partir duquel une facture
    // impayée déclenche un rappel (voir ProcessDueNotifications).
    'rappel_facture_jours_avant' => 3,

    // Délai par défaut appliqué à une facture créée sans date_echeance
    // explicite (voir InvoiceController::store()).
    'echeance_facture_defaut_jours' => 30,
];
