<?php

namespace Tests\Feature;

use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Audit sécurité C1 : un compte rh ou direction (users.create/users.update)
 * pouvait se donner le rôle administrateur, ou changer le mot de passe de
 * l'administrateur puis se connecter à sa place.
 */
class UserAccessEscalationTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structure;

    private Site $site;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structure = Structure::factory()->create();
        $this->site = Site::factory()->for($this->structure)->create();
        $this->admin = $this->userWithRole('administrateur', ['email' => 'admin@clinique.test', 'password' => Hash::make('admin-secret')]);
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->for($this->structure)->create($attributes);
        $user->assignRole($role);
        $user->sites()->sync([$this->site->id]);

        return $user;
    }

    private function payload(User $user, array $overrides = []): array
    {
        return [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => $user->roles->first()->name,
            'site_ids' => [$this->site->id],
            ...$overrides,
        ];
    }

    public static function nonAdminManagers(): array
    {
        return [['rh'], ['direction']];
    }

    #[DataProvider('nonAdminManagers')]
    public function test_a_manager_cannot_promote_themselves_to_administrateur(string $role): void
    {
        $actor = $this->userWithRole($role);

        $this->actingAs($actor)
            ->putJson("/api/users/{$actor->id}", $this->payload($actor, ['role' => 'administrateur']))
            ->assertForbidden();

        $this->assertFalse($actor->fresh()->hasRole('administrateur'));
    }

    #[DataProvider('nonAdminManagers')]
    public function test_a_manager_cannot_take_over_the_administrator_account(string $role): void
    {
        $actor = $this->userWithRole($role);

        // Scénario prouvé pendant l'audit : réinitialiser le mot de passe de
        // l'administrateur, puis se connecter avec.
        $this->actingAs($actor)
            ->putJson("/api/users/{$this->admin->id}", $this->payload($this->admin, ['password' => 'pirate-1234']))
            ->assertForbidden();

        $this->actingAs($actor)
            ->putJson("/api/users/{$this->admin->id}", $this->payload($this->admin, ['email' => 'pirate@clinique.test']))
            ->assertForbidden();

        $this->assertTrue(Hash::check('admin-secret', $this->admin->fresh()->password));
        $this->assertSame('admin@clinique.test', $this->admin->fresh()->email);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => 'admin@clinique.test', 'password' => 'pirate-1234'])
            ->assertStatus(422);
    }

    #[DataProvider('nonAdminManagers')]
    public function test_a_manager_cannot_create_an_account_with_more_rights_than_their_own(string $role): void
    {
        $actor = $this->userWithRole($role);

        foreach (['administrateur', 'medecin'] as $targetRole) {
            $this->actingAs($actor)->postJson('/api/users', [
                'first_name' => 'Nouveau',
                'last_name' => 'Compte',
                'email' => "nouveau-{$targetRole}@clinique.test",
                'password' => 'password-1234',
                'role' => $targetRole,
                'site_ids' => [$this->site->id],
            ])->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['email' => 'nouveau-administrateur@clinique.test']);
        $this->assertDatabaseMissing('users', ['email' => 'nouveau-medecin@clinique.test']);
    }

    public function test_a_manager_cannot_reset_another_users_password_or_change_their_role(): void
    {
        $rh = $this->userWithRole('rh');
        $medecin = $this->userWithRole('medecin', ['password' => Hash::make('medecin-secret')]);

        $this->actingAs($rh)
            ->putJson("/api/users/{$medecin->id}", $this->payload($medecin, ['password' => 'pirate-1234']))
            ->assertForbidden();

        $this->actingAs($rh)
            ->putJson("/api/users/{$medecin->id}", $this->payload($medecin, ['role' => 'secretaire']))
            ->assertForbidden();

        $this->assertTrue(Hash::check('medecin-secret', $medecin->fresh()->password));
        $this->assertTrue($medecin->fresh()->hasRole('medecin'));
    }

    public function test_a_manager_can_still_edit_the_profile_of_a_non_administrator(): void
    {
        $rh = $this->userWithRole('rh');
        $medecin = $this->userWithRole('medecin');

        $this->actingAs($rh)
            ->putJson("/api/users/{$medecin->id}", $this->payload($medecin, ['phone' => '+221 77 000 00 00']))
            ->assertOk();

        $this->assertSame('+221 77 000 00 00', $medecin->fresh()->phone);
    }

    public function test_the_role_list_only_offers_roles_the_user_may_assign(): void
    {
        $rh = $this->userWithRole('rh');

        $roles = $this->actingAs($rh)->getJson('/api/users/roles')->assertOk()->json('data');
        $this->assertNotContains('administrateur', $roles);
        $this->assertNotContains('medecin', $roles);

        $all = $this->actingAs($this->admin)->getJson('/api/users/roles')->assertOk()->json('data');
        $this->assertContains('administrateur', $all);
        $this->assertContains('medecin', $all);
    }

    public function test_an_administrator_keeps_full_control(): void
    {
        $medecin = $this->userWithRole('medecin');

        $this->actingAs($this->admin)
            ->putJson("/api/users/{$medecin->id}", $this->payload($medecin, ['role' => 'directeur_medical', 'password' => 'nouveau-1234']))
            ->assertOk();

        $this->assertTrue($medecin->fresh()->hasRole('directeur_medical'));
        $this->assertTrue(Hash::check('nouveau-1234', $medecin->fresh()->password));
    }
}
