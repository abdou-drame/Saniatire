<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * REMÉDIATION DE SÉCURITÉ PONCTUELLE — PAS UNE PRATIQUE NORMALE.
 *
 * Contexte : jusqu'au correctif de User/PlatformAdmin::getActivitylogOptions()
 * (ajout de ->logExcept(['password'])), logFillable() faisait entrer le hash
 * bcrypt du mot de passe dans `properties.attributes.password` (et
 * `properties.old.password`) des entrées du journal d'audit à chaque création
 * de compte ou changement de mot de passe. Une fuite accidentelle : un hash
 * n'a rien à faire dans un journal consultable.
 *
 * Le journal est append-only (migration 2026_08_29_000001 : trigger
 * PostgreSQL `activity_log_append_only`, triggers SQLite
 * `activity_log_no_update`/`activity_log_no_delete`) et bloque donc toute
 * correction. Cette migration est l'unique exception, documentée :
 *
 *  1. elle ne fait rien s'il n'y a aucune ligne concernée (trigger intact) ;
 *  2. sinon elle désactive la protection, le temps de cette seule correction ;
 *  3. elle ne modifie QUE la valeur de la clé `password` dans
 *     `properties.attributes` / `properties.old`, remplacée par le marqueur
 *     ci-dessous, et `updated_at` (pour que la modification soit datée).
 *     Aucune ligne n'est supprimée ; description, événement, auteur (causer),
 *     sujet, structure, IP et created_at — qui a fait quoi et quand —
 *     restent strictement intacts ;
 *  4. elle réactive la protection immédiatement après (finally). Sous
 *     PostgreSQL le DDL est transactionnel et la migration tourne dans une
 *     transaction : en cas d'échec, tout est annulé, désactivation comprise ;
 *  5. elle journalise sa propre exécution dans activity_log (log_name
 *     `securite`, événement `remediation_securite`) avec la liste des
 *     entrées corrigées : une ligne dont `updated_at` diffère de
 *     `created_at` s'explique par cette entrée.
 *
 * Après elle, le trigger protège de nouveau normalement (vérifié par
 * tests/Feature/RedactPasswordHashesFromActivityLogMigrationTest). Ne pas
 * copier ce schéma pour une autre « correction » du journal.
 *
 * down() ne fait rien volontairement : remettre des hashes serait réintroduire
 * la fuite, et ils ne sont de toute façon plus connus.
 */
return new class extends Migration
{
    public const MARKER = '[rédigé — fuite de sécurité corrigée]';

    public function up(): void
    {
        $redacted = [];

        foreach (
            DB::table('activity_log')
                ->whereRaw('CAST(properties AS TEXT) LIKE ?', ['%"password"%'])
                ->orderBy('id')
                ->get(['id', 'properties']) as $row
        ) {
            $properties = json_decode((string) $row->properties, true);

            if (! is_array($properties)) {
                continue;
            }

            $changed = false;

            foreach (['attributes', 'old'] as $section) {
                if (isset($properties[$section]) && is_array($properties[$section])
                    && array_key_exists('password', $properties[$section])
                    && $properties[$section]['password'] !== self::MARKER) {
                    $properties[$section]['password'] = self::MARKER;
                    $changed = true;
                }
            }

            if ($changed) {
                $redacted[$row->id] = $properties;
            }
        }

        if ($redacted === []) {
            return;
        }

        $now = now();

        $this->disableAppendOnlyProtection();

        try {
            foreach ($redacted as $id => $properties) {
                DB::table('activity_log')->where('id', $id)->update([
                    'properties' => json_encode($properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => $now,
                ]);
            }
        } finally {
            $this->enableAppendOnlyProtection();
        }

        DB::table('activity_log')->insert([
            'log_name' => 'securite',
            'description' => 'Remédiation de sécurité ponctuelle : hashes de mot de passe retirés du journal d\'audit',
            'event' => 'remediation_securite',
            'properties' => json_encode([
                'migration' => '2026_10_03_000003_redact_password_hashes_from_activity_log',
                'motif' => 'Fuite accidentelle de hashes de mot de passe (logFillable sans logExcept password). Exception unique, pas une pratique normale.',
                'champ_modifie' => 'properties.attributes.password / properties.old.password',
                'marqueur' => self::MARKER,
                'nombre_entrees' => count($redacted),
                'activity_ids' => array_keys($redacted),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Volontairement vide (voir docblock).
    }

    private function disableAppendOnlyProtection(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS activity_log_no_update;
                DROP TRIGGER IF EXISTS activity_log_no_delete;
            SQL);

            return;
        }

        DB::unprepared('ALTER TABLE activity_log DISABLE TRIGGER activity_log_append_only');
    }

    /** SQL identique à 2026_08_29_000001_add_append_only_trigger_to_activity_log_table. */
    private function enableAppendOnlyProtection(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER activity_log_no_update
                BEFORE UPDATE ON activity_log
                BEGIN
                    SELECT RAISE(ABORT, "Le journal d'audit est en lecture seule (append-only) : modification refusée.");
                END;

                CREATE TRIGGER activity_log_no_delete
                BEFORE DELETE ON activity_log
                BEGIN
                    SELECT RAISE(ABORT, "Le journal d'audit est en lecture seule (append-only) : suppression refusée.");
                END;
            SQL);

            return;
        }

        DB::unprepared('ALTER TABLE activity_log ENABLE TRIGGER activity_log_append_only');
    }
};
