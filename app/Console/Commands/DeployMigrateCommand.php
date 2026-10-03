<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Lancée au démarrage du conteneur (voir nixpacks.toml, [start]) avant le
 * serveur HTTP : applique les migrations en attente à chaque déploiement.
 *
 * Dokploy n'a pas de phase « release » exécutée une seule fois par
 * déploiement : chaque réplica du service démarre cette commande. Sous
 * PostgreSQL, un verrou consultatif de session (pg_advisory_lock) sérialise
 * donc les instances : la première applique les migrations, les suivantes
 * attendent qu'elle ait fini puis constatent qu'il n'y a plus rien à faire.
 * Aucune instance ne démarre ainsi avec un schéma partiellement migré, et le
 * verrou ne dépend d'aucune table (fonctionne aussi sur une base vierge).
 *
 * En cas d'échec d'une migration, l'exception remonte, la commande sort en
 * erreur et le `&&` du démarrage empêche le serveur HTTP de se lancer.
 */
class DeployMigrateCommand extends Command
{
    protected $signature = 'deploy:migrate';

    protected $description = 'Applique les migrations en attente au déploiement, une instance à la fois (verrou PostgreSQL).';

    /** Clé arbitraire mais stable du verrou consultatif. */
    private const LOCK_KEY = 735_120_001;

    public function handle(): int
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'pgsql') {
            $this->warn("Driver {$connection->getDriverName()} : migrations lancées sans verrou inter-instances.");

            return $this->call('migrate', ['--force' => true]);
        }

        if (! $connection->selectOne('SELECT pg_try_advisory_lock(?) AS locked', [self::LOCK_KEY])->locked) {
            $this->info('[deploy:migrate] Une autre instance applique les migrations, attente du verrou…');
            $connection->select('SELECT pg_advisory_lock(?)', [self::LOCK_KEY]);
        }

        $this->info('[deploy:migrate] Verrou obtenu, application des migrations en attente.');

        try {
            $exitCode = $this->call('migrate', ['--force' => true]);
        } finally {
            $connection->select('SELECT pg_advisory_unlock(?)', [self::LOCK_KEY]);
        }

        $this->info("[deploy:migrate] Terminé (code {$exitCode}).");

        return $exitCode;
    }
}
