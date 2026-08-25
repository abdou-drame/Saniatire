<?php

namespace Tests\Feature;

use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class Step9AuditLogEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function patientPayload(): array
    {
        return Patient::factory()->make()->only(['first_name', 'last_name', 'sex', 'birth_date']);
    }

    public function test_only_conformite_and_administrateur_can_read_the_audit_log(): void
    {
        $structure = Structure::factory()->create();

        $auditor = User::factory()->for($structure)->create();
        $auditor->assignRole('conformite');

        $nurse = User::factory()->for($structure)->create();
        $nurse->assignRole('infirmier');

        $this->actingAs($auditor)->getJson('/api/audit-logs')->assertOk();
        $this->actingAs($nurse)->getJson('/api/audit-logs')->assertForbidden();
    }

    public function test_filters_by_user_action_table_and_period(): void
    {
        $structure = Structure::factory()->create();

        $auditor = User::factory()->for($structure)->create();
        $auditor->assignRole('conformite');

        $doctor = User::factory()->for($structure)->create();
        $doctor->assignRole('medecin');

        $this->travelTo(now()->subDays(10));
        $oldPatient = $this->actingAs($doctor)->postJson('/api/patients', $this->patientPayload())->assertCreated();
        $this->travelBack();

        $this->travelTo(now());
        $newPatient = $this->actingAs($doctor)->postJson('/api/patients', $this->patientPayload())->assertCreated();
        $this->travelBack();

        // Filtre par utilisateur : seules les entrées causées par $doctor.
        $byUser = $this->actingAs($auditor)
            ->getJson("/api/audit-logs?user_id={$doctor->id}")
            ->assertOk();
        $this->assertGreaterThanOrEqual(2, count($byUser->json('data')));

        // Filtre par table.
        $byTable = $this->actingAs($auditor)
            ->getJson('/api/audit-logs?table=Patient')
            ->assertOk();
        foreach ($byTable->json('data') as $row) {
            $this->assertStringContainsString('Patient', $row['subject_type']);
        }

        // Filtre par période : uniquement l'entrée récente.
        $byPeriod = $this->actingAs($auditor)
            ->getJson('/api/audit-logs?from='.now()->subDay()->toDateString().'&to='.now()->toDateString())
            ->assertOk();
        $recentIds = collect($byPeriod->json('data'))->pluck('subject_id')->all();
        $this->assertContains($newPatient->json('data.id'), $recentIds);
        $this->assertNotContains($oldPatient->json('data.id'), $recentIds);
    }

    public function test_audit_log_is_isolated_per_tenant(): void
    {
        $structureA = Structure::factory()->create();
        $auditorA = User::factory()->for($structureA)->create();
        $auditorA->assignRole('conformite');

        $structureB = Structure::factory()->create();
        $doctorB = User::factory()->for($structureB)->create();
        $doctorB->assignRole('medecin');

        $patientB = $this->actingAs($doctorB)->postJson('/api/patients', $this->patientPayload())->assertCreated();

        $response = $this->actingAs($auditorA)->getJson('/api/audit-logs')->assertOk();

        $subjectIds = collect($response->json('data'))
            ->filter(fn ($row) => str_contains($row['subject_type'], 'Patient'))
            ->pluck('subject_id')
            ->all();

        $this->assertNotContains($patientB->json('data.id'), $subjectIds);
    }

    public function test_audit_entries_cannot_be_updated_even_by_an_administrator(): void
    {
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create();
        $admin->assignRole('administrateur');
        $admin->generateTwoFactorSecret();
        $admin->confirmTwoFactor();

        $this->actingAs($admin)->postJson('/api/patients', $this->patientPayload())->assertCreated();

        $activity = Activity::where('structure_id', $structure->id)->firstOrFail();

        $this->expectException(\RuntimeException::class);
        $activity->description = 'falsifie';
        $activity->save();
    }

    public function test_audit_entry_deletion_is_blocked_even_by_an_administrator(): void
    {
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create();
        $admin->assignRole('administrateur');
        $admin->generateTwoFactorSecret();
        $admin->confirmTwoFactor();

        $this->actingAs($admin)->postJson('/api/patients', $this->patientPayload())->assertCreated();

        $activity = Activity::where('structure_id', $structure->id)->firstOrFail();

        $this->expectException(\RuntimeException::class);
        $activity->delete();
    }

    public function test_audit_entries_cannot_be_bulk_deleted_bypassing_model_events(): void
    {
        // Activity::deleting()/updating() (AppServiceProvider) ne se
        // déclenchent que sur le cycle de vie Eloquent d'un modèle
        // individuel. Une requête de masse ne passe jamais par ces events
        // — c'est pourquoi la protection réelle est un trigger au niveau
        // de la table (migration 2026_08_29_000001), qui s'applique
        // indépendamment du chemin applicatif emprunté.
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create();
        $admin->assignRole('administrateur');
        $admin->generateTwoFactorSecret();
        $admin->confirmTwoFactor();

        $this->actingAs($admin)->postJson('/api/patients', $this->patientPayload())->assertCreated();

        $activity = Activity::where('structure_id', $structure->id)->firstOrFail();

        $this->expectException(\Illuminate\Database\QueryException::class);
        Activity::where('id', $activity->id)->delete();
    }
}
