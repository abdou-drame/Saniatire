<?php

namespace Tests\Feature;

use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrudSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * Administration plateforme : la création de structure n'est plus
     * accessible via /api/structures (faille fermée, voir
     * StructureController et PlatformAdminTest) — seul le guard `platform`
     * peut désormais onboarder une nouvelle structure.
     */
    public function test_structure_can_be_onboarded_via_the_platform_admin_api(): void
    {
        $response = $this->postJson('/api/platform/structures', []);
        $response->assertUnauthorized();

        $platformAdmin = PlatformAdmin::create([
            'name' => 'Admin Plateforme',
            'email' => 'platform-admin@example.test',
            'password' => Hash::make('un-mot-de-passe-solide'),
        ]);

        $response = $this->actingAs($platformAdmin, 'platform')->postJson('/api/platform/structures', [
            'code' => 'NEW-001',
            'legal_name' => 'Cabinet du Plateau',
            'type' => 'cabinet',
            'admin_first_name' => 'Awa',
            'admin_last_name' => 'Koné',
            'admin_email' => 'awa.kone@cabinet-plateau.example',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('structures', ['code' => 'NEW-001']);
    }

    public function test_a_structures_own_administrateur_can_no_longer_create_structures_via_the_api(): void
    {
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create();
        $admin->assignRole('administrateur');

        $this->actingAs($admin)->postJson('/api/structures', [
            'code' => 'NEW-002',
            'legal_name' => 'Ne Devrait Pas Exister',
            'type' => 'cabinet',
        ])->assertStatus(405);
    }

    public function test_admin_can_create_a_site_and_a_user_with_a_role(): void
    {
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create();
        $admin->assignRole('administrateur');

        $siteResponse = $this->actingAs($admin)->postJson('/api/sites', [
            'name' => 'Site Central',
        ]);
        $siteResponse->assertCreated();
        $siteId = $siteResponse->json('data.id');

        $this->assertDatabaseHas('sites', ['id' => $siteId, 'structure_id' => $structure->id]);

        $userResponse = $this->actingAs($admin)->postJson('/api/users', [
            'first_name' => 'Marie',
            'last_name' => 'Yao',
            'email' => 'marie.yao@example.com',
            'password' => 'password123',
            'role' => 'secretaire',
            'site_ids' => [$siteId],
        ]);

        $userResponse->assertCreated();
        $newUser = User::find($userResponse->json('data.id'));

        $this->assertTrue($newUser->hasRole('secretaire'));
        $this->assertTrue($newUser->sites->contains('id', $siteId));
        $this->assertEquals($structure->id, $newUser->structure_id);
    }

    public function test_a_user_without_permission_cannot_create_a_patient(): void
    {
        $structure = Structure::factory()->create();
        $biomedical = User::factory()->for($structure)->create();
        $biomedical->assignRole('biomedical'); // no patients.create permission

        $this->actingAs($biomedical)->postJson('/api/patients', [
            'first_name' => 'Jean',
            'last_name' => 'Kouassi',
            'sex' => 'M',
            'birth_date' => '1985-05-05',
        ])->assertForbidden();
    }
}
