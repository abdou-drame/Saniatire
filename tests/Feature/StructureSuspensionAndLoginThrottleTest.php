<?php

namespace Tests\Feature;

use App\Domain\Patient\Models\Patient;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Prescripteur\Models\ExternalPrescriber;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Suspension (is_active=false) et archivage (soft delete) réels d'une
 * structure : refus de connexion sur les 3 guards rattachés à une
 * structure, rejet des tokens émis avant la suspension
 * (EnsureTenantContext), archivage plateforme en consultation seule. Plus
 * le limiteur `login` sur les routes de connexion, la journalisation des
 * connexions plateforme, et le retrait de structures.delete au rôle
 * administrateur.
 */
class StructureSuspensionAndLoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private const SUSPENDED = "L'accès à cette structure est suspendu. Contactez l'administration de la plateforme.";

    private const ARCHIVED = "Cette structure n'est plus active sur la plateforme.";

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function makePlatformAdmin(): PlatformAdmin
    {
        return PlatformAdmin::create([
            'name' => 'Admin Plateforme',
            'email' => 'platform-admin@example.test',
            'password' => Hash::make('un-mot-de-passe-solide'),
        ]);
    }

    public function test_staff_login_is_refused_with_an_explicit_message_when_the_structure_is_suspended(): void
    {
        $structure = Structure::factory()->create(['is_active' => false]);
        $user = User::factory()->for($structure)->create();

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(403)
            ->assertJsonPath('message', self::SUSPENDED)
            ->assertJsonMissingPath('token');
    }

    public function test_staff_login_is_refused_when_the_structure_is_archived(): void
    {
        $structure = Structure::factory()->create();
        $user = User::factory()->for($structure)->create();
        $structure->delete();

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(403)
            ->assertJsonPath('message', self::ARCHIVED);
    }

    public function test_a_wrong_password_on_a_suspended_structure_still_reads_as_invalid_credentials(): void
    {
        // Le motif de suspension n'est révélé qu'après vérification du mot
        // de passe : pas d'énumération des comptes d'une structure suspendue.
        $structure = Structure::factory()->create(['is_active' => false]);
        $user = User::factory()->for($structure)->create();

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'mauvais'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Identifiants invalides.');
    }

    public function test_patient_portal_login_is_refused_when_the_structure_is_suspended(): void
    {
        $structure = Structure::factory()->create(['is_active' => false]);
        $patient = Patient::factory()->withPortalActivated()->for($structure)->create();

        $this->postJson('/api/portail-patient/login', ['email' => $patient->email, 'password' => 'password'])
            ->assertStatus(403)
            ->assertJsonPath('message', self::SUSPENDED)
            ->assertJsonMissingPath('token');
    }

    public function test_prescriber_portal_login_is_refused_when_the_structure_is_suspended(): void
    {
        $structure = Structure::factory()->create(['is_active' => false]);
        $prescriber = ExternalPrescriber::factory()->withPortalActivated()->for($structure)->create();

        $this->postJson('/api/portail-prescripteur/login', ['email' => $prescriber->email, 'password' => 'password'])
            ->assertStatus(403)
            ->assertJsonPath('message', self::SUSPENDED)
            ->assertJsonMissingPath('token');
    }

    public function test_the_2fa_challenge_does_not_issue_a_token_if_the_structure_was_suspended_in_between(): void
    {
        $structure = Structure::factory()->create();
        $user = User::factory()->for($structure)->create();
        $user->assignRole('medecin');
        $secret = $user->generateTwoFactorSecret();
        $user->confirmTwoFactor();

        $challenge = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->json('challenge');
        $this->assertNotNull($challenge);

        $structure->update(['is_active' => false]);

        $this->postJson('/api/auth/2fa/challenge', [
            'challenge' => $challenge,
            'code' => app(Google2FA::class)->getCurrentOtp($secret),
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', self::SUSPENDED)
            ->assertJsonMissingPath('token');
    }

    public function test_tokens_issued_before_the_suspension_are_rejected_on_all_three_guards_and_work_again_after_reactivation(): void
    {
        $structure = Structure::factory()->create();
        $staffToken = User::factory()->for($structure)->create()->createToken('api')->plainTextToken;
        $patientToken = Patient::factory()->withPortalActivated()->for($structure)->create()
            ->createToken('patient-portal')->plainTextToken;
        $prescriberToken = ExternalPrescriber::factory()->withPortalActivated()->for($structure)->create()
            ->createToken('prescriber-portal')->plainTextToken;

        $calls = [
            [$staffToken, '/api/auth/me'],
            [$patientToken, '/api/portail-patient/me'],
            [$prescriberToken, '/api/portail-prescripteur/me'],
        ];

        foreach ($calls as [$token, $uri]) {
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->getJson($uri)->assertOk();
        }

        $structure->update(['is_active' => false]);

        foreach ($calls as [$token, $uri]) {
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->getJson($uri)
                ->assertStatus(401)
                ->assertJsonPath('message', self::SUSPENDED);
        }

        // Pas de révocation des tokens à la suspension : une réactivation
        // rend l'accès sans forcer chaque compte à se reconnecter.
        $structure->update(['is_active' => true]);

        foreach ($calls as [$token, $uri]) {
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->getJson($uri)->assertOk();
        }
    }

    public function test_the_four_login_routes_and_the_2fa_challenge_are_throttled_then_unblocked_after_the_window(): void
    {
        $routes = [
            '/api/auth/login' => ['email' => 'staff@example.test', 'password' => 'mauvais'],
            '/api/portail-patient/login' => ['email' => 'patient@example.test', 'password' => 'mauvais'],
            '/api/portail-prescripteur/login' => ['email' => 'prescripteur@example.test', 'password' => 'mauvais'],
            '/api/platform/login' => ['email' => 'plateforme@example.test', 'password' => 'mauvais'],
            '/api/auth/2fa/challenge' => ['challenge' => 'challenge-inconnu', 'code' => '000000'],
        ];

        foreach ($routes as $uri => $payload) {
            for ($i = 1; $i <= 5; $i++) {
                $this->postJson($uri, $payload)->assertStatus(422);
            }

            $blocked = $this->postJson($uri, $payload)->assertStatus(429);
            $this->assertStringStartsWith('Trop de tentatives de connexion. Réessayez dans ', $blocked->json('message'));
            $this->assertNotNull($blocked->headers->get('Retry-After'));
        }

        $this->travel(61)->seconds();

        foreach ($routes as $uri => $payload) {
            $this->postJson($uri, $payload)->assertStatus(422);
        }
    }

    public function test_the_throttle_is_per_email_so_one_targeted_account_does_not_block_the_others(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->postJson('/api/platform/login', ['email' => 'cible@example.test', 'password' => 'mauvais']);
        }

        $this->postJson('/api/platform/login', ['email' => 'cible@example.test', 'password' => 'mauvais'])->assertStatus(429);
        $this->postJson('/api/platform/login', ['email' => 'autre@example.test', 'password' => 'mauvais'])->assertStatus(422);
    }

    public function test_platform_logins_are_recorded_in_the_platform_audit_log(): void
    {
        $admin = $this->makePlatformAdmin();

        $this->postJson('/api/platform/login', ['email' => $admin->email, 'password' => 'mauvais'])->assertStatus(422);
        $this->postJson('/api/platform/login', ['email' => $admin->email, 'password' => 'un-mot-de-passe-solide'])->assertOk();

        $entries = Activity::query()->where('log_name', 'administration_plateforme')->orderBy('id')->get();
        $this->assertCount(2, $entries);

        $this->assertSame('echec_connexion_plateforme', $entries[0]->properties['action']);
        $this->assertSame($admin->email, $entries[0]->properties['email']);

        $this->assertSame('connexion_plateforme', $entries[1]->properties['action']);
        $this->assertSame(PlatformAdmin::class, $entries[1]->causer_type);
        $this->assertSame($admin->id, (int) $entries[1]->causer_id);
        $this->assertNull($entries[1]->structure_id);
        $this->assertNotNull($entries[1]->ip_address);

        // Visible via l'endpoint du journal plateforme.
        $this->actingAs($admin, 'platform')
            ->getJson('/api/platform/audit-logs')
            ->assertOk()
            ->assertJsonPath('total', 2);
    }

    public function test_the_structure_administrator_no_longer_holds_structures_delete(): void
    {
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create();
        $admin->assignRole('administrateur');

        $this->assertFalse($admin->hasPermissionTo('structures.delete'));
        $this->assertFalse($admin->hasPermissionTo('structures.create'));
        $this->assertTrue($admin->hasPermissionTo('structures.update'));

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/structures/{$structure->id}")
            ->assertStatus(403);

        $this->assertNotSoftDeleted($structure);
    }

    public function test_the_data_migration_revokes_structures_delete_on_an_already_seeded_database(): void
    {
        $role = Role::findByName('administrateur', 'sanctum');
        $role->givePermissionTo('structures.delete');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertTrue($role->fresh()->hasPermissionTo('structures.delete'));

        $migration = require database_path('migrations/2026_10_02_000001_revoke_structures_delete_from_administrateur.php');
        $migration->up();
        $migration->up(); // idempotente

        $this->assertFalse($role->fresh()->hasPermissionTo('structures.delete'));
    }

    public function test_the_platform_can_archive_a_structure_which_then_stays_readable_but_frozen(): void
    {
        $platformAdmin = $this->makePlatformAdmin();
        $structure = Structure::factory()->create();
        $module = $structure->modules()->create(['module' => 'laboratoire', 'is_active' => true]);
        $staffToken = User::factory()->for($structure)->create()->createToken('api')->plainTextToken;

        $this->actingAs($platformAdmin, 'platform')
            ->postJson("/api/platform/structures/{$structure->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.id', $structure->id);

        // Soft delete uniquement : la ligne existe toujours.
        $this->assertSoftDeleted($structure);
        $this->assertNotNull(Structure::withTrashed()->find($structure->id));

        $log = Activity::query()->where('log_name', 'administration_plateforme')
            ->where('properties->action', 'archivage_structure')->sole();
        $this->assertSame($structure->id, $log->structure_id);
        $this->assertSame($platformAdmin->id, (int) $log->causer_id);

        // Consultable par la plateforme, marquée archivée.
        $this->getJson("/api/platform/structures/{$structure->id}")
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.archived_at', fn ($value) => $value !== null);
        $this->getJson("/api/platform/structures/{$structure->id}/modules")->assertOk();
        $this->assertContains(
            $structure->id,
            collect($this->getJson('/api/platform/structures')->assertOk()->json('data'))->pluck('id')->all(),
        );

        // Mais plus aucune écriture.
        foreach (['activate', 'deactivate', 'archive'] as $action) {
            $this->postJson("/api/platform/structures/{$structure->id}/{$action}")->assertStatus(409);
        }
        $this->patchJson("/api/platform/structures/{$structure->id}/modules/{$module->id}", ['is_active' => false])
            ->assertStatus(409);
        $this->assertFalse(Structure::withTrashed()->find($structure->id)->is_active);

        // Côté structure : plus aucun accès, token existant compris.
        $this->app['auth']->forgetGuards();
        $this->withToken($staffToken)->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('message', self::ARCHIVED);
    }

    public function test_archiving_is_reserved_to_the_platform_guard(): void
    {
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create();
        $admin->assignRole('administrateur');

        $this->postJson("/api/platform/structures/{$structure->id}/archive")->assertStatus(401);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/platform/structures/{$structure->id}/archive")
            ->assertStatus(401);

        $this->assertNotSoftDeleted($structure);
    }
}
