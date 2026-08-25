<?php

namespace Tests\Feature;

use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_structure_can_be_onboarded_via_the_api(): void
    {
        $response = $this->postJson('/api/structures', []);
        $response->assertUnauthorized();

        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create();
        $admin->assignRole('administrateur');

        $response = $this->actingAs($admin)->postJson('/api/structures', [
            'code' => 'NEW-001',
            'legal_name' => 'Cabinet du Plateau',
            'type' => 'cabinet',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('structures', ['code' => 'NEW-001']);
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
