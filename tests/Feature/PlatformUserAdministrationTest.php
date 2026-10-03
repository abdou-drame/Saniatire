<?php

namespace Tests\Feature;

use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Livraison C : gestion des comptes du personnel par l'administrateur de
 * plateforme (administrateurs d'une structure, déblocage, réinitialisation
 * de mot de passe). Comme PlatformAdministrationAuditTest, tout passe par de
 * vrais tokens Bearer avec Auth::forgetGuards() avant chaque requête : le
 * RequestGuard de Sanctum mémorise l'utilisateur résolu pour toute la
 * méthode de test, et ces tests alternent volontairement les guards
 * platform et sanctum. Le token platform réel vérifie aussi au passage le
 * piège TenantScope (currentStructureId() null sous ce guard).
 */
class PlatformUserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private ?string $platformToken = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function platform(string $method, string $uri, array $data = []): TestResponse
    {
        if ($this->platformToken === null) {
            PlatformAdmin::create([
                'name' => 'Admin Plateforme',
                'email' => 'platform-admin@example.test',
                'password' => Hash::make('un-mot-de-passe-solide'),
            ]);

            Auth::forgetGuards();
            $this->platformToken = $this->postJson('/api/platform/login', [
                'email' => 'platform-admin@example.test',
                'password' => 'un-mot-de-passe-solide',
            ])->assertOk()->json('token');
        }

        Auth::forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$this->platformToken}")->json($method, $uri, $data);
    }

    private function staff(string $token, string $method, string $uri, array $data = []): TestResponse
    {
        Auth::forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$token}")->json($method, $uri, $data);
    }

    private function login(string $email, string $password): TestResponse
    {
        Auth::forgetGuards();

        return $this->withHeaders(['Authorization' => ''])->postJson('/api/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
    }

    private function makeAdministrator(Structure $structure, array $attributes = []): User
    {
        $user = User::factory()->for($structure)->create($attributes);
        $user->assignRole('administrateur');

        return $user;
    }

    private function assertAuditedWithoutPassword(string $action, int $structureId, ?string $password = null): void
    {
        $activity = Activity::query()
            ->where('log_name', 'administration_plateforme')
            ->where('structure_id', $structureId)
            ->get()
            ->first(fn (Activity $activity) => $activity->properties['action'] === $action);

        $this->assertNotNull($activity, "Entrée d'audit {$action} attendue.");
        $this->assertTrue($activity->properties['hors_isolation']);

        $properties = $activity->properties->toArray();
        $this->assertArrayNotHasKey('password', $properties);
        $this->assertArrayNotHasKey('generated_password', $properties);
        if ($password !== null) {
            $this->assertStringNotContainsString($password, json_encode($properties));
            $this->assertStringNotContainsString($password, $activity->description);
        }
    }

    public function test_listing_is_limited_to_the_structure_and_to_the_administrateur_role(): void
    {
        $structure = Structure::factory()->create();
        $other = Structure::factory()->create();

        $zoe = $this->makeAdministrator($structure, ['last_name' => 'Zongo']);
        $awa = $this->makeAdministrator($structure, ['last_name' => 'Bamba']);
        $nonAdmin = User::factory()->for($structure)->create();
        $nonAdmin->assignRole('medecin');
        $foreignAdmin = $this->makeAdministrator($other);

        $response = $this->platform('GET', "/api/platform/structures/{$structure->id}/administrators");

        $response->assertOk();
        $this->assertSame([$awa->id, $zoe->id], array_column($response->json('data'), 'id'));
        $response->assertJsonStructure(['data' => [[
            'id', 'structure_id', 'first_name', 'last_name', 'email', 'is_active',
            'must_change_password', 'is_locked', 'locked_until', 'failed_login_attempts',
            'last_login_at', 'created_at',
        ]]]);
        $this->assertNotContains($foreignAdmin->id, array_column($response->json('data'), 'id'));
        $this->assertNotContains($nonAdmin->id, array_column($response->json('data'), 'id'));
    }

    public function test_creating_an_administrator_returns_a_one_time_password_and_forces_its_change(): void
    {
        $structure = Structure::factory()->create();

        $response = $this->platform('POST', "/api/platform/structures/{$structure->id}/administrators", [
            'first_name' => 'Mariam',
            'last_name' => 'Diallo',
            'email' => 'mariam.diallo@example.test',
        ]);

        $response->assertCreated();
        $password = $response->json('generated_password');
        $this->assertNotEmpty($password);
        $this->assertNotEmpty($response->json('message'));
        $this->assertTrue($response->json('data.must_change_password'));
        $this->assertTrue($response->json('data.is_active'));
        $this->assertSame($structure->id, $response->json('data.structure_id'));

        $created = User::query()->where('email', 'mariam.diallo@example.test')->firstOrFail();
        $this->assertTrue($created->hasRole('administrateur'));
        $this->assertTrue($created->must_change_password);

        // Le mot de passe n'est restitué nulle part ailleurs : la liste ne le contient pas.
        $list = $this->platform('GET', "/api/platform/structures/{$structure->id}/administrators");
        $this->assertStringNotContainsString($password, $list->getContent());

        $token = $this->login('mariam.diallo@example.test', $password)->assertOk()->json('token');
        $this->staff($token, 'GET', '/api/patients')->assertStatus(423);

        $this->assertAuditedWithoutPassword('creation_administrateur', $structure->id, $password);
    }

    public function test_creating_an_administrator_in_an_archived_structure_is_refused(): void
    {
        $structure = Structure::factory()->create();
        $structure->delete();

        $this->platform('POST', "/api/platform/structures/{$structure->id}/administrators", [
            'first_name' => 'Mariam',
            'last_name' => 'Diallo',
            'email' => 'mariam.diallo@example.test',
        ])->assertStatus(409);

        $this->assertFalse(User::withoutGlobalScopes()->where('email', 'mariam.diallo@example.test')->exists());
    }

    public function test_creating_an_administrator_with_a_duplicate_email_is_rejected(): void
    {
        $structure = Structure::factory()->create();
        $other = Structure::factory()->create();
        User::factory()->for($other)->create(['email' => 'deja.pris@example.test']);

        $this->platform('POST', "/api/platform/structures/{$structure->id}/administrators", [
            'first_name' => 'Mariam',
            'last_name' => 'Diallo',
            'email' => 'deja.pris@example.test',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_deactivation_revokes_tokens_and_blocks_login_then_activation_restores_access(): void
    {
        $structure = Structure::factory()->create();
        $admin = $this->makeAdministrator($structure);

        $token = $this->login($admin->email, 'password')->assertOk()->json('token');
        $this->staff($token, 'GET', '/api/auth/me')->assertOk();

        $this->platform('POST', "/api/platform/structures/{$structure->id}/administrators/{$admin->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertSame(0, $admin->tokens()->count());
        $this->staff($token, 'GET', '/api/auth/me')->assertUnauthorized();
        $this->login($admin->email, 'password')->assertStatus(422);
        $this->assertAuditedWithoutPassword('desactivation_administrateur', $structure->id);

        $this->platform('POST', "/api/platform/structures/{$structure->id}/administrators/{$admin->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->login($admin->email, 'password')->assertOk();
        $this->assertAuditedWithoutPassword('activation_administrateur', $structure->id);
    }

    public function test_deactivating_an_administrator_of_another_structure_or_a_non_administrator_returns_404(): void
    {
        $structure = Structure::factory()->create();
        $other = Structure::factory()->create();
        $foreignAdmin = $this->makeAdministrator($other);
        $nonAdmin = User::factory()->for($structure)->create();
        $nonAdmin->assignRole('medecin');

        $this->platform('POST', "/api/platform/structures/{$structure->id}/administrators/{$foreignAdmin->id}/deactivate")
            ->assertNotFound();
        $this->platform('POST', "/api/platform/structures/{$structure->id}/administrators/{$nonAdmin->id}/deactivate")
            ->assertNotFound();

        $this->assertTrue($foreignAdmin->fresh()->is_active);
        $this->assertTrue($nonAdmin->fresh()->is_active);
    }

    public function test_deactivating_an_administrator_of_an_archived_structure_is_refused(): void
    {
        $structure = Structure::factory()->create();
        $admin = $this->makeAdministrator($structure);
        $structure->delete();

        $this->platform('POST', "/api/platform/structures/{$structure->id}/administrators/{$admin->id}/deactivate")
            ->assertStatus(409);
    }

    public function test_unlocking_a_locked_account_allows_login_again(): void
    {
        $structure = Structure::factory()->create();
        $user = User::factory()->for($structure)->create();
        $user->assignRole('medecin');
        $user->forceFill(['failed_login_attempts' => 3, 'locked_until' => now()->addMinutes(15)])->save();

        $this->login($user->email, 'password')->assertStatus(423);

        $this->platform('POST', "/api/platform/users/{$user->id}/unlock")
            ->assertOk()
            ->assertJsonPath('data.is_locked', false)
            ->assertJsonPath('data.locked_until', null)
            ->assertJsonPath('data.failed_login_attempts', 0);

        $this->login($user->email, 'password')->assertOk();
        $this->assertAuditedWithoutPassword('deblocage_compte', $structure->id);
    }

    public function test_resetting_a_password_revokes_tokens_unlocks_and_forces_a_change(): void
    {
        $structure = Structure::factory()->create();
        $user = User::factory()->for($structure)->create();
        $user->assignRole('medecin');

        $oldToken = $this->login($user->email, 'password')->assertOk()->json('token');
        $user->forceFill(['failed_login_attempts' => 2, 'locked_until' => now()->addMinutes(15)])->save();

        $response = $this->platform('POST', "/api/platform/users/{$user->id}/reset-password");
        $response->assertOk()
            ->assertJsonPath('data.must_change_password', true)
            ->assertJsonPath('data.is_locked', false)
            ->assertJsonPath('data.failed_login_attempts', 0);
        $newPassword = $response->json('generated_password');
        $this->assertNotEmpty($newPassword);
        $this->assertNotEmpty($response->json('message'));

        $this->staff($oldToken, 'GET', '/api/auth/me')->assertUnauthorized();
        $this->login($user->email, 'password')->assertStatus(422);
        $newToken = $this->login($user->email, $newPassword)->assertOk()->json('token');
        $this->staff($newToken, 'GET', '/api/patients')->assertStatus(423);

        $this->assertAuditedWithoutPassword('reinitialisation_mot_de_passe', $structure->id, $newPassword);
    }

    public function test_no_password_hash_ever_reaches_the_activity_log(): void
    {
        $structure = Structure::factory()->create();
        $user = User::factory()->for($structure)->create();

        $this->platform('POST', "/api/platform/structures/{$structure->id}/administrators", [
            'first_name' => 'Awa', 'last_name' => 'Koné', 'email' => 'awa.kone@example.test',
        ])->assertCreated();
        $this->platform('POST', "/api/platform/users/{$user->id}/reset-password")->assertOk();

        foreach (Activity::all() as $activity) {
            $this->assertStringNotContainsString('$2y$', json_encode($activity->properties));
            $this->assertArrayNotHasKey('password', $activity->properties['attributes'] ?? []);
        }
    }

    public function test_resetting_a_password_in_an_archived_structure_is_refused(): void
    {
        $structure = Structure::factory()->create();
        $user = User::factory()->for($structure)->create();
        $structure->delete();

        $this->platform('POST', "/api/platform/users/{$user->id}/reset-password")->assertStatus(409);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_unknown_user_returns_404(): void
    {
        $this->platform('POST', '/api/platform/users/999999/unlock')->assertNotFound();
        $this->platform('POST', '/api/platform/users/999999/reset-password')->assertNotFound();
    }

    public function test_a_staff_sanctum_token_cannot_call_these_routes(): void
    {
        $structure = Structure::factory()->create();
        $admin = $this->makeAdministrator($structure);
        $token = $admin->createToken('sanctum-test')->plainTextToken;

        $endpoints = [
            ['GET', "/api/platform/structures/{$structure->id}/administrators"],
            ['POST', "/api/platform/structures/{$structure->id}/administrators"],
            ['POST', "/api/platform/structures/{$structure->id}/administrators/{$admin->id}/deactivate"],
            ['POST', "/api/platform/structures/{$structure->id}/administrators/{$admin->id}/activate"],
            ['POST', "/api/platform/users/{$admin->id}/unlock"],
            ['POST', "/api/platform/users/{$admin->id}/reset-password"],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $this->staff($token, $method, $uri)->assertUnauthorized();
        }

        $this->assertTrue(Hash::check('password', $admin->fresh()->password));
    }
}
