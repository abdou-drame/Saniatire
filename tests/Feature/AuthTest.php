<?php

namespace Tests\Feature;

use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->for(Structure::factory())->create([
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user']);
    }

    /**
     * Régression : AuthController::login() chargeait `roles`/`structure` mais
     * omettait `sites` de l'eager-load, donc la clé `sites` était absente du
     * JSON (whenLoaded()) — le frontend (useSiteSelection) lisait alors
     * `user.sites` comme un tableau vide et affichait "Aucun site n'existe"
     * même quand le compte était bien rattaché à un site.
     */
    public function test_login_response_includes_the_users_sites(): void
    {
        $structure = Structure::factory()->create();
        $site = Site::factory()->for($structure)->create();
        $user = User::factory()->for($structure)->create([
            'password' => Hash::make('correct-password'),
        ]);
        $user->sites()->attach($site->id);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertOk()->assertJsonPath('user.sites.0.id', $site->id);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = User::factory()->for(Structure::factory())->create([
            'password' => Hash::make('correct-password'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_account_locks_after_too_many_failed_attempts(): void
    {
        $user = User::factory()->for(Structure::factory())->create([
            'password' => Hash::make('correct-password'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertStatus(423);
        $this->assertTrue($user->fresh()->isLocked());
    }
}
