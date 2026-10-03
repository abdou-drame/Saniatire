<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Remédiation ponctuelle des hashes de mot de passe fuités dans le journal
 * (migration 2026_10_03_000003). Les lignes « historiques » sont insérées
 * directement (INSERT reste autorisé par le trigger append-only), puis la
 * migration est rejouée sur elles.
 */
class RedactPasswordHashesFromActivityLogMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MARKER = '[rédigé — fuite de sécurité corrigée]';

    private const HASH_NEW = '$2y$12$abcdefghijklmnopqrstuuMhP1Qq3vY8m7wq2b9nJ0yZr5s6t7u8v';

    private const HASH_OLD = '$2y$12$zyxwvutsrqponmlkjihgfeO6c2Rr4uX9n8vp3a0mK1xYq4w5e6r7t';

    private function migration(): object
    {
        return require database_path('migrations/2026_10_03_000003_redact_password_hashes_from_activity_log.php');
    }

    private function insertLog(array $row): int
    {
        return DB::table('activity_log')->insertGetId(array_merge([
            'log_name' => 'user',
            'description' => 'updated',
            'event' => 'updated',
            'subject_type' => 'App\\Domain\\User\\Models\\User',
            'subject_id' => 42,
            'causer_type' => 'App\\Domain\\User\\Models\\User',
            'causer_id' => 7,
            'ip_address' => '10.0.0.1',
            'created_at' => '2026-09-01 10:00:00',
            'updated_at' => '2026-09-01 10:00:00',
        ], $row));
    }

    private function seedLeakedRows(): array
    {
        return [
            'created' => $this->insertLog([
                'description' => 'created',
                'event' => 'created',
                'properties' => json_encode(['attributes' => ['email' => 'a@x.test', 'password' => self::HASH_NEW]]),
            ]),
            'updated' => $this->insertLog([
                'properties' => json_encode([
                    'attributes' => ['first_name' => 'Awa', 'password' => self::HASH_NEW],
                    'old' => ['first_name' => 'Ava', 'password' => self::HASH_OLD],
                ]),
            ]),
            'unrelated' => $this->insertLog([
                'log_name' => 'administration_plateforme',
                'description' => 'reinitialisation_mot_de_passe',
                'event' => null,
                'properties' => json_encode(['action' => 'reinitialisation_mot_de_passe']),
            ]),
        ];
    }

    private function assertAppendOnly(int $id): void
    {
        try {
            DB::table('activity_log')->where('id', $id)->update(['description' => 'falsifié']);
            $this->fail('UPDATE aurait dû être refusé par le trigger.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            DB::table('activity_log')->where('id', $id)->delete();
            $this->fail('DELETE aurait dû être refusé par le trigger.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
    }

    public function test_trigger_blocks_the_correction_before_the_migration(): void
    {
        $ids = $this->seedLeakedRows();

        $this->assertAppendOnly($ids['updated']);
    }

    public function test_only_the_password_value_is_redacted_and_who_did_what_when_is_intact(): void
    {
        $ids = $this->seedLeakedRows();
        $before = DB::table('activity_log')->whereIn('id', $ids)->get()->keyBy('id');

        $this->migration()->up();

        $after = DB::table('activity_log')->whereIn('id', $ids)->get()->keyBy('id');
        $this->assertCount(3, $after, 'Aucune ligne ne doit être supprimée.');

        $created = json_decode($after[$ids['created']]->properties, true);
        $this->assertSame(['email' => 'a@x.test', 'password' => self::MARKER], $created['attributes']);

        $updated = json_decode($after[$ids['updated']]->properties, true);
        $this->assertSame(['first_name' => 'Awa', 'password' => self::MARKER], $updated['attributes']);
        $this->assertSame(['first_name' => 'Ava', 'password' => self::MARKER], $updated['old']);

        foreach ([$ids['created'], $ids['updated']] as $id) {
            foreach (['log_name', 'description', 'event', 'subject_type', 'subject_id', 'causer_type', 'causer_id', 'ip_address', 'structure_id', 'batch_uuid', 'created_at'] as $column) {
                $this->assertEquals($before[$id]->$column, $after[$id]->$column, "Colonne {$column} modifiée (ligne {$id}).");
            }
            $this->assertNotEquals($before[$id]->updated_at, $after[$id]->updated_at, 'La modification doit être datée.');
        }

        $this->assertEquals((array) $before[$ids['unrelated']], (array) $after[$ids['unrelated']]);

        $this->assertFalse(
            DB::table('activity_log')->get()->contains(fn ($row) => str_contains((string) $row->properties, '$2y$')),
            'Plus aucun hash ne doit subsister dans le journal.'
        );
    }

    public function test_trigger_protects_again_after_the_migration(): void
    {
        $ids = $this->seedLeakedRows();

        $this->migration()->up();

        $this->assertAppendOnly($ids['updated']);
        $this->assertAppendOnly($ids['unrelated']);

        $remediation = DB::table('activity_log')->where('event', 'remediation_securite')->value('id');
        $this->assertAppendOnly($remediation);
    }

    public function test_the_remediation_itself_is_journaled(): void
    {
        $ids = $this->seedLeakedRows();

        $this->migration()->up();

        $entries = DB::table('activity_log')->where('log_name', 'securite')->get();
        $this->assertCount(1, $entries);
        $this->assertSame('remediation_securite', $entries[0]->event);

        $properties = json_decode($entries[0]->properties, true);
        $this->assertSame('2026_10_03_000003_redact_password_hashes_from_activity_log', $properties['migration']);
        $this->assertSame(2, $properties['nombre_entrees']);
        $this->assertSame([$ids['created'], $ids['updated']], $properties['activity_ids']);
        $this->assertSame(self::MARKER, $properties['marqueur']);
    }

    public function test_nothing_happens_when_there_is_no_leak(): void
    {
        $ids = $this->seedLeakedRows();
        $this->migration()->up();
        $count = DB::table('activity_log')->count();

        // Second passage : plus rien à corriger, aucune nouvelle entrée.
        $this->migration()->up();

        $this->assertSame($count, DB::table('activity_log')->count());
        $this->assertAppendOnly($ids['created']);
    }
}
