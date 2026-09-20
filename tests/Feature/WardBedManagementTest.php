<?php

namespace Tests\Feature;

use App\Domain\Hospitalisation\Models\Ward;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WardBedManagementTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Structure $structureB;

    private Site $siteA;

    private Site $siteB;

    private User $adminA;

    private User $infirmierA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structureA = Structure::factory()->create();
        $this->structureB = Structure::factory()->create();
        $this->siteA = Site::factory()->for($this->structureA)->create();
        $this->siteB = Site::factory()->for($this->structureB)->create();

        $this->adminA = User::factory()->for($this->structureA)->create();
        $this->adminA->assignRole('administrateur');

        $this->infirmierA = User::factory()->for($this->structureA)->create();
        $this->infirmierA->assignRole('infirmier');
    }

    public function test_creating_a_ward_and_bed_via_api_defaults_the_bed_to_libre(): void
    {
        $ward = $this->actingAs($this->adminA)->postJson('/api/wards', [
            'site_id' => $this->siteA->id,
            'name' => 'Médecine interne',
        ])->assertCreated()->json('data');

        $bed = $this->actingAs($this->adminA)->postJson('/api/beds', [
            'site_id' => $this->siteA->id,
            'ward_id' => $ward['id'],
            'room_number' => '204',
            'bed_label' => 'A',
        ])->assertCreated()->json('data');

        $this->assertSame('libre', $bed['status']);
    }

    public function test_an_unauthorized_role_cannot_create_a_ward_or_a_bed(): void
    {
        $this->actingAs($this->infirmierA)->postJson('/api/wards', [
            'site_id' => $this->siteA->id,
            'name' => 'Médecine interne',
        ])->assertForbidden();

        $ward = Ward::factory()->for($this->structureA)->for($this->siteA)->create();

        $this->actingAs($this->infirmierA)->postJson('/api/beds', [
            'site_id' => $this->siteA->id,
            'ward_id' => $ward->id,
            'room_number' => '204',
            'bed_label' => 'A',
        ])->assertForbidden();
    }

    public function test_wards_from_one_structure_are_never_visible_from_another(): void
    {
        $wardA = Ward::factory()->for($this->structureA)->for($this->siteA)->create();

        $adminB = User::factory()->for($this->structureB)->create();
        $adminB->assignRole('administrateur');

        $this->actingAs($adminB)->getJson('/api/wards')->assertOk()->assertJsonMissing(['id' => $wardA->id]);

        $this->actingAs($adminB)->getJson("/api/wards/{$wardA->id}")->assertNotFound();
    }
}
