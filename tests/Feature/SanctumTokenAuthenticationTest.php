<?php

namespace Tests\Feature;

use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * actingAs() authenticates a test request by calling Auth::setUser()
 * directly, bypassing Sanctum's real token-lookup flow entirely. That flow
 * (Laravel\Sanctum\Guard::__invoke -> PersonalAccessToken::tokenable ->
 * User::find()) is what triggers TenantScope on the User model itself, so
 * it needs its own coverage with a genuine bearer token to catch any
 * regression of the re-entrancy guard in TenantScope::currentStructureId().
 */
class SanctumTokenAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_real_bearer_token_authenticates_and_scopes_tenant_scoped_queries(): void
    {
        $structureA = Structure::factory()->create();
        $structureB = Structure::factory()->create();

        $user = User::factory()->for($structureA)->create();
        $user->assignRole('administrateur');
        $user->generateTwoFactorSecret();
        $user->confirmTwoFactor();
        $token = $user->createToken('test')->plainTextToken;

        Patient::factory()->for($structureA)->count(2)->create();
        Patient::factory()->for($structureB)->count(3)->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/patients');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_a_real_bearer_token_authenticates_the_users_endpoint(): void
    {
        $structure = Structure::factory()->create();

        $user = User::factory()->for($structure)->create();
        $user->assignRole('administrateur');
        $user->generateTwoFactorSecret();
        $user->confirmTwoFactor();
        $token = $user->createToken('test')->plainTextToken;

        User::factory()->for($structure)->count(2)->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/users');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }
}
