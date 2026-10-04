<?php

namespace Tests\Feature;

use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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

    public function test_an_account_required_by_the_admin_is_blocked_from_the_api_until_setup_is_complete(): void
    {
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create(['password' => Hash::make('correct-password')]);
        $admin->assignRole('direction');
        $admin->forceFill(['two_factor_required' => true])->save();

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

    public function test_an_account_without_the_requirement_is_never_blocked(): void
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
        $admin->assignRole('direction');
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
        $admin->assignRole('direction');
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

    public function test_2fa_cannot_be_disabled_when_the_admin_requires_it(): void
    {
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create(['password' => Hash::make('correct-password')]);
        $admin->assignRole('direction');
        $admin->forceFill(['two_factor_required' => true])->save();
        $admin->generateTwoFactorSecret();
        $admin->confirmTwoFactor();

        $this->actingAs($admin)
            ->postJson('/api/auth/2fa/disable', ['password' => 'correct-password'])
            ->assertStatus(422);

        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());
    }

    public function test_no_account_is_required_to_use_2fa_by_default_whatever_its_role(): void
    {
        $structure = Structure::factory()->create();

        foreach (['direction', 'directeur_medical', 'psychiatre', 'administrateur'] as $role) {
            $user = User::factory()->for($structure)->create(['password' => Hash::make('correct-password')]);
            $user->assignRole($role);

            $login = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'correct-password'])->assertOk();

            $this->assertFalse($login->json('two_factor_setup_required'), "Rôle {$role}");
            $this->assertNotNull($login->json('token'));
        }
    }

    public function test_an_administrator_with_2fa_enabled_is_really_challenged_at_login(): void
    {
        // Avant correctif : l'administrateur voyait « 2FA activée » mais
        // recevait un jeton directement, sans code.
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create(['password' => Hash::make('correct-password')]);
        $admin->assignRole('administrateur');
        $admin->generateTwoFactorSecret();
        $admin->confirmTwoFactor();

        $login = $this->postJson('/api/auth/login', ['email' => $admin->email, 'password' => 'correct-password'])->assertOk();

        $this->assertTrue($login->json('two_factor_required'));
        $this->assertNull($login->json('token'));
    }

    public function test_only_the_administrator_can_require_2fa_for_an_account(): void
    {
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create();
        $admin->assignRole('administrateur');
        $rh = User::factory()->for($structure)->create();
        $rh->assignRole('rh');
        $target = User::factory()->for($structure)->create(['password' => Hash::make('correct-password')]);
        $target->assignRole('secretaire');

        $this->actingAs($rh)
            ->putJson("/api/users/{$target->id}/two-factor-requirement", ['required' => true])
            ->assertForbidden();
        $this->assertFalse($target->fresh()->requiresTwoFactor());

        // Le champ n'est pas modifiable via l'édition classique d'un compte.
        $this->actingAs($admin)->putJson("/api/users/{$target->id}", [
            'first_name' => $target->first_name,
            'last_name' => $target->last_name,
            'email' => $target->email,
            'role' => 'secretaire',
            'two_factor_required' => true,
        ])->assertOk();
        $this->assertFalse($target->fresh()->requiresTwoFactor());

        $this->actingAs($admin)
            ->putJson("/api/users/{$target->id}/two-factor-requirement", ['required' => true])
            ->assertOk()
            ->assertJsonPath('data.two_factor_required', true)
            ->assertJsonPath('data.two_factor_enabled', false);

        // Exigée : à la connexion suivante, le compte doit la configurer.
        Auth::forgetGuards();
        $login = $this->postJson('/api/auth/login', ['email' => $target->email, 'password' => 'correct-password'])->assertOk();
        $this->assertTrue($login->json('two_factor_setup_required'));

        Auth::forgetGuards();
        $this->withToken($login->json('token'))->getJson('/api/patients')->assertStatus(423);

        // Et l'administrateur peut lever l'exigence.
        Auth::forgetGuards();
        $this->actingAs($admin)
            ->putJson("/api/users/{$target->id}/two-factor-requirement", ['required' => false])
            ->assertOk();
        $this->assertFalse($target->fresh()->requiresTwoFactor());
    }
}
