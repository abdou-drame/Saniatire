<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Étape 9 §3 : Activity::updating()/Activity::deleting() (voir
 * AppServiceProvider::boot()) bloquent les mutations passant par le cycle
 * de vie Eloquent d'un modèle individuel, mais une requête de masse
 * (`Activity::where(...)->update()/->delete()`, ou tout SQL brut via la
 * même connexion applicative) ne déclenche jamais ces events — elle les
 * contourne entièrement. Un trigger PostgreSQL au niveau de la table
 * ferme ce contournement : il s'applique à toute UPDATE/DELETE sur
 * `activity_log`, quel que soit le chemin applicatif emprunté pour y
 * arriver (Eloquent individuel, requête de masse, SQL brut), tant que la
 * connexion utilisée est celle de l'application (un accès superutilisateur
 * PostgreSQL capable de désactiver les triggers reste, comme toute
 * intervention infra, hors du périmètre applicatif de cette étape).
 */
return new class extends Migration
{
    /**
     * La suite de tests tourne sur SQLite en mémoire (voir phpunit.xml),
     * tandis que le développement/production utilisent PostgreSQL — les
     * deux moteurs supportent des triggers empêchant UPDATE/DELETE, mais
     * avec une syntaxe différente (PL/pgSQL vs `RAISE(ABORT, ...)`).
     */
    public function up(): void
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

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_activity_log_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Le journal d''audit est en lecture seule (append-only) : % refusé.', TG_OP;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER activity_log_append_only
            BEFORE UPDATE OR DELETE ON activity_log
            FOR EACH ROW EXECUTE FUNCTION prevent_activity_log_mutation();
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS activity_log_no_update;
                DROP TRIGGER IF EXISTS activity_log_no_delete;
            SQL);

            return;
        }

        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS activity_log_append_only ON activity_log;
            DROP FUNCTION IF EXISTS prevent_activity_log_mutation();
        SQL);
    }
};
