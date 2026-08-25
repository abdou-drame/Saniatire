<?php

namespace Tests\Feature;

use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RolePermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_secretary_cannot_delete_users_an_administrator_only_action(): void
    {
        $structure = Structure::factory()->create();
        $secretary = User::factory()->for($structure)->create();
        $secretary->assignRole('secretaire');

        $someUser = User::factory()->for($structure)->create();

        $this->actingAs($secretary)
            ->deleteJson("/api/users/{$someUser->id}")
            ->assertForbidden();
    }

    public function test_a_secretary_cannot_update_the_structure_an_administrator_or_direction_only_action(): void
    {
        $structure = Structure::factory()->create();
        $secretary = User::factory()->for($structure)->create();
        $secretary->assignRole('secretaire');

        $this->actingAs($secretary)
            ->putJson("/api/structures/{$structure->id}", ['legal_name' => 'Hacked Name'])
            ->assertForbidden();
    }

    public function test_an_inactive_user_cannot_authenticate(): void
    {
        $user = User::factory()->for(Structure::factory())->create([
            'password' => Hash::make('correct-password'),
            'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertStatus(422);
    }
}
