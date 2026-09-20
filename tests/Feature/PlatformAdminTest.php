<?php

namespace Tests\Feature;

use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Administration plateforme : guard `platform` dédié (isolation
 * structurelle, voir config/auth.php), création de structure + premier
 * administrateur (mot de passe généré + must_change_password), gestion des
 * modules activés par structure, et journal d'audit dédié
 * (administration_plateforme). Voir aussi CrossGuardIsolationTest pour le
 * patron d'étanchéité entre guards, étendu ici au guard platform.
 */
class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function makePlatformAdmin(): PlatformAdmin
    {
        return PlatformAdmin::create([
            'name' => 'Admin Plateforme',
            'email' => 'platform-admin@example.test',
            'password' => Hash::make('un-mot-de-passe-solide'),
        ]);
    }

    public function test_a_platform_admin_can_login_and_receive_a_platform_token(): void
    {
        $this->makePlatformAdmin();

        $response = $this->postJson('/api/platform/login', [
            'email' => 'platform-admin@example.test',
            'password' => 'un-mot-de-passe-solide',
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('token'));
        $this->assertSame('platform-admin@example.test', $response->json('platform_admin.email'));
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->makePlatformAdmin();

        $this->postJson('/api/platform/login', [
            'email' => 'platform-admin@example.test',
            'password' => 'mauvais-mot-de-passe',
        ])->assertStatus(422);
    }

    public function test_a_platform_admin_can_create_a_structure_with_its_first_admin_account(): void
    {
        $admin = $this->makePlatformAdmin();

        $response = $this->actingAs($admin, 'platform')->postJson('/api/platform/structures', [
            'code' => 'CLN-TEST',
            'legal_name' => 'Clinique de Test',
            'type' => 'clinique',
            'admin_first_name' => 'Awa',
            'admin_last_name' => 'Koné',
            'admin_email' => 'awa.kone@clinique-test.example',
        ]);

        $response->assertCreated();

        $structureId = $response->json('structure.id');
        $this->assertNotNull($structureId);
        $this->assertNotEmpty($response->json('admin_generated_password'));

        $createdAdmin = User::query()->where('email', 'awa.kone@clinique-test.example')->first();
        $this->assertNotNull($createdAdmin);
        $this->assertSame($structureId, $createdAdmin->structure_id);
        $this->assertTrue($createdAdmin->must_change_password);
        $this->assertTrue($createdAdmin->hasRole('administrateur'));

        $this->assertSame(
            count(RolePermissionSeeder::MODULES),
            Structure::find($structureId)->modules()->where('is_active', true)->count()
        );
    }

    public function test_creating_a_structure_produces_an_administration_plateforme_audit_entry(): void
    {
        $admin = $this->makePlatformAdmin();

        $response = $this->actingAs($admin, 'platform')->postJson('/api/platform/structures', [
            'code' => 'CLN-AUDIT',
            'legal_name' => 'Clinique Audit',
            'type' => 'clinique',
            'admin_first_name' => 'Ibrahim',
            'admin_last_name' => 'Traoré',
            'admin_email' => 'ibrahim.traore@clinique-audit.example',
        ]);
        $response->assertCreated();
        $structureId = $response->json('structure.id');

        $activity = Activity::query()
            ->where('log_name', 'administration_plateforme')
            ->where('structure_id', $structureId)
            ->where('description', 'like', '%creation_structure%')
            ->first();

        $this->assertNotNull($activity, 'Expected an administration_plateforme audit entry for the new structure.');
        $this->assertTrue($activity->properties['hors_isolation']);
        $this->assertSame($structureId, $activity->structure_id);
    }

    /**
     * Construit directement l'état "premier administrateur avec mot de
     * passe généré" sans passer par une requête HTTP sur le guard
     * `platform` — CrossGuardIsolationTest documente qu'alterner plusieurs
     * guards `actingAs()` au sein d'une même méthode de test peut produire
     * un faux résultat propre au harness de test (le guard résolu par
     * Laravel est mémorisé pour la durée du conteneur applicatif partagé
     * par la méthode). Cette méthode ne touche donc que le guard sanctum.
     */
    public function test_a_new_structures_first_admin_must_change_their_generated_password_before_using_the_api(): void
    {
        $structure = Structure::factory()->create();
        $newAdmin = User::factory()->for($structure)->create(['must_change_password' => true]);
        $newAdmin->assignRole('administrateur');

        $this->actingAs($newAdmin)
            ->getJson('/api/patients')
            ->assertStatus(423);

        $this->actingAs($newAdmin)
            ->postJson('/api/auth/change-password', [
                'password' => 'un-nouveau-mot-de-passe',
                'password_confirmation' => 'un-nouveau-mot-de-passe',
            ])
            ->assertOk();

        $newAdmin->refresh();
        $this->assertFalse($newAdmin->must_change_password);

        $this->actingAs($newAdmin)
            ->getJson('/api/patients')
            ->assertOk();
    }

    public function test_a_structures_administrateur_role_can_no_longer_create_structures(): void
    {
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create();
        $admin->assignRole('administrateur');

        // 405, pas 403 : la route store() n'existe plus du tout sur
        // StructureController (routes/api.php ->except(['store'])) — la
        // faille est fermée par suppression de la route elle-même, pas
        // seulement par un refus de permission.
        $this->actingAs($admin)->postJson('/api/structures', [
            'code' => 'CLN-FAILLE',
            'legal_name' => 'Ne Devrait Pas Exister',
            'type' => 'clinique',
        ])->assertStatus(405);
    }

    public function test_a_sanctum_guard_token_cannot_reach_platform_routes(): void
    {
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create();
        $admin->assignRole('administrateur');

        $this->actingAs($admin)
            ->getJson('/api/platform/structures')
            ->assertUnauthorized();
    }

    public function test_a_platform_token_cannot_reach_ordinary_tenant_scoped_routes(): void
    {
        $platformAdmin = $this->makePlatformAdmin();

        $this->actingAs($platformAdmin, 'platform')
            ->getJson('/api/patients')
            ->assertUnauthorized();
    }

    public function test_the_platform_admin_can_toggle_a_structure_module(): void
    {
        $platformAdmin = $this->makePlatformAdmin();
        $structure = Structure::factory()->create();

        $this->actingAs($platformAdmin, 'platform')->postJson('/api/platform/structures', [
            'code' => 'CLN-MOD',
            'legal_name' => 'Clinique Modules',
            'type' => 'clinique',
            'admin_first_name' => 'Sekou',
            'admin_last_name' => 'Camara',
            'admin_email' => 'sekou.camara@clinique-mod.example',
        ])->assertCreated();

        $newStructure = Structure::query()->where('code', 'CLN-MOD')->firstOrFail();
        $module = $newStructure->modules()->first();

        $response = $this->actingAs($platformAdmin, 'platform')
            ->patchJson("/api/platform/structures/{$newStructure->id}/modules/{$module->id}", [
                'is_active' => false,
            ]);

        $response->assertOk();
        $this->assertFalse($response->json('data.is_active'));

        $module->refresh();
        $this->assertFalse($module->is_active);
        $this->assertNotNull($module->deactivated_at);
    }

    public function test_the_platform_audit_log_only_lists_administration_plateforme_entries(): void
    {
        $platformAdmin = $this->makePlatformAdmin();

        $this->actingAs($platformAdmin, 'platform')->postJson('/api/platform/structures', [
            'code' => 'CLN-LOG',
            'legal_name' => 'Clinique Log',
            'type' => 'clinique',
            'admin_first_name' => 'Mariam',
            'admin_last_name' => 'Diarra',
            'admin_email' => 'mariam.diarra@clinique-log.example',
        ])->assertCreated();

        // Entrée d'un autre log_name créée directement (sans passer par une
        // requête HTTP sur le guard sanctum) pour rester sur un seul guard
        // dans cette méthode de test — voir la note sur les faux négatifs
        // liés au harness dans CrossGuardIsolationTest.
        $structure = Structure::factory()->create();
        $patient = \App\Domain\Patient\Models\Patient::factory()->for($structure)->create();
        activity('created')->performedOn($patient)->log('Entrée hors administration_plateforme, ne doit jamais apparaître ici.');

        $response = $this->actingAs($platformAdmin, 'platform')->getJson('/api/platform/audit-logs');
        $response->assertOk();

        $logNames = collect($response->json('data'))->pluck('description');
        $this->assertTrue($logNames->every(fn ($description) => str_contains($description, "l'administrateur de plateforme")));
    }
}
