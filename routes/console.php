<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Étape 7a : envoie les rappels de RDV programmés échus et génère les
// rappels d'échéance de facture. Programmée ici mais son exécution réelle
// dépend d'un worker schedule:work/cron déjà hors périmètre (même statut
// que les canaux SMS/WhatsApp/push simulés).
Schedule::command('notifications:process-due')->everyMinute();
