<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Les liens d'activation de portail et de réinitialisation de mot de passe
 * (jeton en clair dans l'URL) étaient conservés tels quels dans
 * notification_logs.contenu_final, consultable par les administrateurs de
 * structure. NotificationDispatcher ne les enregistre plus ; cette
 * migration masque ceux déjà stockés. Seule l'URL porteuse du jeton est
 * remplacée, le reste de la ligne est intact. Idempotente : une ligne déjà
 * masquée ne contient plus de « token= ».
 */
return new class extends Migration
{
    private const MASK = '[lien confidentiel, non conservé]';

    public function up(): void
    {
        DB::table('notification_logs')
            ->whereIn('type_evenement', ['patient_portal_activation', 'patient_password_reset'])
            ->where('contenu_final', 'like', '%token=%')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('notification_logs')->where('id', $row->id)->update([
                        'contenu_final' => preg_replace('#\S*token=\S*#', self::MASK, $row->contenu_final),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Irréversible par nature : les jetons masqués ne sont pas conservés.
    }
};
