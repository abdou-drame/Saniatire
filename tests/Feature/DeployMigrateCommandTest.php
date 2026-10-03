<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le chemin PostgreSQL (verrou consultatif) a été vérifié sur une vraie
 * instance PostgreSQL ; les tests tournent sous SQLite, où la commande
 * applique les migrations sans verrou.
 */
class DeployMigrateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_already_applied_migrations_are_not_run_again(): void
    {
        $this->artisan('deploy:migrate')
            ->expectsOutputToContain('Nothing to migrate')
            ->assertSuccessful();
    }
}
