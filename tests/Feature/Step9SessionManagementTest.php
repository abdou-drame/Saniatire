<?php

namespace Tests\Feature;

use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Laravel/Sanctum's RequestGuard caches the user it resolved for the
 * duration of a test method (the AuthManager singleton persists across
 * several ->getJson()/->withHeader() calls within one test). Auth::forgetGuards()
 * is called between requests meant to authenticate as a *different* token
 * than the previous request — without it, the guard silently keeps
 * returning whichever user it resolved first, masking real
 * revocation/authorization behaviour.
 */
class Step9SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_user_can_list_their_own_active_sessions(): void
    {
        $structure = Structure::factory()->create();
        $user = User::factory()->for($structure)->create();
        $user->assignRole('secretaire');

        $tokenA = $user->createToken('appareil-a')->accessToken;
        $tokenB = $user->createToken('appareil-b')->accessToken;

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/sessions')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($tokenA->id, $ids);
        $this->assertContains($tokenB->id, $ids);
    }

    public function test_revoking_a_session_immediately_rejects_its_token(): void
    {
        $structure = Structure::factory()->create();
        $user = User::factory()->for($structure)->create();
        $user->assignRole('secretaire');

        $tokenResult = $user->createToken('appareil-a');
        $plainText = $tokenResult->plainTextToken;
        $tokenId = $tokenResult->accessToken->id;

        // Le token fonctionne avant révocation.
        $this->withHeader('Authorization', "Bearer {$plainText}")
            ->getJson('/api/patients')
            ->assertOk();

        Auth::forgetGuards();
        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/auth/sessions/{$tokenId}")
            ->assertOk();

        // Rejeté après révocation : le token n'existe plus en base.
        Auth::forgetGuards();
        $this->withHeader('Authorization', "Bearer {$plainText}")
            ->getJson('/api/patients')
            ->assertUnauthorized();
    }

    public function test_revoke_others_keeps_the_current_token_but_kills_the_rest(): void
    {
        $structure = Structure::factory()->create();
        $user = User::factory()->for($structure)->create();
        $user->assignRole('secretaire');

        $current = $user->createToken('appareil-courant');
        $other = $user->createToken('autre-appareil');
        $otherPlain = $other->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$current->plainTextToken}")
            ->postJson('/api/auth/sessions/revoke-others')
            ->assertOk();

        // Le token courant fonctionne toujours.
        Auth::forgetGuards();
        $this->withHeader('Authorization', "Bearer {$current->plainTextToken}")
            ->getJson('/api/patients')
            ->assertOk();

        // L'autre token a été révoqué.
        Auth::forgetGuards();
        $this->withHeader('Authorization', "Bearer {$otherPlain}")
            ->getJson('/api/patients')
            ->assertUnauthorized();
    }

    public function test_a_user_cannot_revoke_another_users_session(): void
    {
        $structure = Structure::factory()->create();
        $userA = User::factory()->for($structure)->create();
        $userA->assignRole('secretaire');
        $userB = User::factory()->for($structure)->create();
        $userB->assignRole('secretaire');

        $tokenB = $userB->createToken('appareil-b');

        $this->actingAs($userA, 'sanctum')
            ->deleteJson("/api/auth/sessions/{$tokenB->accessToken->id}")
            ->assertStatus(404);

        // Le token de B n'a pas été supprimé.
        Auth::forgetGuards();
        $this->withHeader('Authorization', "Bearer {$tokenB->plainTextToken}")
            ->getJson('/api/patients')
            ->assertOk();
    }
}
