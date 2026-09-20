<?php

namespace Tests\Feature;

use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Étape 15 : GET /api/structures/directory alimente le picker de
 * destination du référencement inter-structures. Gardé sur referrals.create
 * (pas structures.view) : voir StructureController::directory(). Ces tests
 * vérifient la garde de permission, l'exclusion de la structure de
 * l'appelant, et l'exclusion des structures inactives.
 */
class StructureDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_user_with_referrals_create_permission_gets_active_structures_excluding_their_own(): void
    {
        $ownStructure = Structure::factory()->create();
        $otherActive = Structure::factory()->create();
        $otherInactive = Structure::factory()->create(['is_active' => false]);

        $medecin = User::factory()->for($ownStructure)->create();
        $medecin->assignRole('medecin');

        $response = $this->actingAs($medecin)
            ->getJson('/api/structures/directory')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($otherActive->id));
        $this->assertFalse($ids->contains($ownStructure->id));
        $this->assertFalse($ids->contains($otherInactive->id));
    }

    public function test_a_user_without_referrals_create_permission_is_forbidden(): void
    {
        $structure = Structure::factory()->create();
        Structure::factory()->create();

        $direction = User::factory()->for($structure)->create();
        $direction->assignRole('direction');

        $this->actingAs($direction)
            ->getJson('/api/structures/directory')
            ->assertForbidden();
    }

    public function test_an_inactive_structure_never_appears_in_the_directory(): void
    {
        $ownStructure = Structure::factory()->create();
        $inactive = Structure::factory()->create(['is_active' => false]);

        $medecin = User::factory()->for($ownStructure)->create();
        $medecin->assignRole('medecin');

        $response = $this->actingAs($medecin)
            ->getJson('/api/structures/directory')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertFalse($ids->contains($inactive->id));
    }
}
