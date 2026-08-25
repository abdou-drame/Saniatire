<?php

namespace Tests\Feature;

use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class Step9TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_role_requiring_2fa_is_blocked_from_the_api_until_setup_is_complete(): void
    {
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create(['password' => Hash::make('correct-password')]);
        $admin->assignRole('administrateur');

        $login = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'correct-password',
        ])->assertOk();

        $this->assertTrue($login->json('two_factor_setup_required'));
        $token = $login->json('token');
        $this->assertNotNull($token, 'A restricted token must still be issued so the user can reach /auth/2fa/setup.');

        // Bloqué : la 2FA obligatoire n'est pas encore configurée.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/patients')
            ->assertStatus(423);

        // Non bloqué : les routes de configuration 2FA restent accessibles.
        $setup = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/2fa/setup')
            ->assertOk();
        $secret = $setup->json('secret');
        $this->assertNotNull($secret);

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $confirm = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/2fa/confirm', ['code' => $code])
            ->assertOk();
        $this->assertCount(8, $confirm->json('recovery_codes'));

        // Le même token, désormais, n'est plus bloqué.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/patients')
            ->assertOk();
    }

    public function test_a_role_not_requiring_2fa_is_never_blocked(): void
    {
        $structure = Structure::factory()->create();
        $secretary = User::factory()->for($structure)->create(['password' => Hash::make('correct-password')]);
        $secretary->assignRole('secretaire');

        $login = $this->postJson('/api/auth/login', [
            'email' => $secretary->email,
            'password' => 'correct-password',
        ])->assertOk();

        $this->assertFalse($login->json('two_factor_setup_required'));

        $this->withHeader('Authorization', 'Bearer '.$login->json('token'))
            ->getJson('/api/patients')
            ->assertOk();
    }

    public function test_login_with_2fa_already_confirmed_returns_a_challenge_instead_of_a_token(): void
    {
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create(['password' => Hash::make('correct-password')]);
        $admin->assignRole('administrateur');
        $secret = $admin->generateTwoFactorSecret();
        $admin->confirmTwoFactor();

        $login = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'correct-password',
        ])->assertOk();

        $this->assertTrue($login->json('two_factor_required'));
        $this->assertNull($login->json('token'));
        $challenge = $login->json('challenge');
        $this->assertNotNull($challenge);

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $exchange = $this->postJson('/api/auth/2fa/challenge', [
            'challenge' => $challenge,
            'code' => $code,
        ])->assertOk();

        $token = $exchange->json('token');
        $this->assertNotNull($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/patients')
            ->assertOk();
    }

    public function test_challenge_accepts_a_recovery_code_exactly_once(): void
    {
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create(['password' => Hash::make('correct-password')]);
        $admin->assignRole('administrateur');
        $admin->generateTwoFactorSecret();
        $codes = $admin->confirmTwoFactor();
        $recoveryCode = $codes[0];

        $login = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'correct-password',
        ])->assertOk();
        $challenge = $login->json('challenge');

        $this->postJson('/api/auth/2fa/challenge', [
            'challenge' => $challenge,
            'recovery_code' => $recoveryCode,
        ])->assertOk();

        // Nouveau challenge, même code de récupération : refusé (usage unique).
        $login2 = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'correct-password',
        ])->assertOk();

        $this->postJson('/api/auth/2fa/challenge', [
            'challenge' => $login2->json('challenge'),
            'recovery_code' => $recoveryCode,
        ])->assertStatus(422);
    }

    public function test_2fa_cannot_be_disabled_for_a_role_that_requires_it(): void
    {
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create(['password' => Hash::make('correct-password')]);
        $admin->assignRole('administrateur');
        $admin->generateTwoFactorSecret();
        $admin->confirmTwoFactor();

        $this->actingAs($admin)
            ->postJson('/api/auth/2fa/disable', ['password' => 'correct-password'])
            ->assertStatus(422);

        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());
    }
}
