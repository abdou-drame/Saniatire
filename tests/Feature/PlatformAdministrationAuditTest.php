<?php

namespace Tests\Feature;

use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Audit dédié demandé par l'utilisateur pour le chantier « Administration
 * plateforme » : chaque section reproduit un point du prompt d'audit
 * (étanchéité du guard, fermeture de structures.create, onboarding
 * structure+admin de bout en bout via de vrais tokens Bearer plutôt que
 * actingAs() pour éviter le piège de mémorisation de guard documenté dans
 * CrossGuardIsolationTest, isolation clinique inverse, modules, audit
 * distinct). Complète PlatformAdminTest et CrossGuardIsolationTest plutôt
 * que de les dupliquer.
 *
 * Note méthodologique (même famille de piège que CrossGuardIsolationTest,
 * mais au sein d'un seul guard cette fois) : le RequestGuard de Sanctum met
 * en cache l'utilisateur résolu (propriété $user de GuardHelpers) pour la
 * durée de vie du conteneur applicatif partagé par une méthode de test —
 * un changement en base (ex. must_change_password) entre deux appels
 * withHeader(...)->json(...) au sein de la MÊME méthode ne serait donc pas
 * revu sans Auth::forgetGuards() explicite. Inexistant en production, où
 * chaque requête HTTP réelle est un processus indépendant qui réinstancie
 * le guard depuis zéro.
 */
class PlatformAdministrationAuditTest extends TestCase
{
    use RefreshDatabase;

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

    /**
     * §1 : au moins cinq endpoints métier normaux, rejetés pour un token
     * platform. Chacun exige le guard sanctum.
     */
    public function test_a_platform_token_is_rejected_on_at_least_five_ordinary_business_endpoints(): void
    {
        $platformAdmin = $this->makePlatformAdmin();
        $token = $this->postJson('/api/platform/login', [
            'email' => 'platform-admin@example.test',
            'password' => 'un-mot-de-passe-solide',
        ])->assertOk()->json('token');

        $endpoints = [
            ['GET', '/api/patients'],
            ['POST', '/api/appointments'],
            ['GET', '/api/invoices'],
            ['GET', '/api/users'],
            ['GET', '/api/dashboards/medical'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $this->withHeader('Authorization', "Bearer {$token}")
                ->json($method, $uri)
                ->assertUnauthorized();
        }
    }

    /**
     * §1 (sens inverse) : un token sanctum classique est rejeté sur
     * l'ensemble des routes /api/platform/*.
     */
    public function test_a_sanctum_token_is_rejected_on_every_platform_route(): void
    {
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create();
        $admin->assignRole('administrateur');
        $token = $admin->createToken('sanctum-test')->plainTextToken;

        $endpoints = [
            ['GET', '/api/platform/me'],
            ['GET', '/api/platform/structures'],
            ['POST', '/api/platform/structures'],
            ['GET', "/api/platform/structures/{$structure->id}/modules"],
            ['GET', '/api/platform/audit-logs'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $this->withHeader('Authorization', "Bearer {$token}")
                ->json($method, $uri)
                ->assertUnauthorized();
        }
    }

    /**
     * §2 : la faille structures.create est fermée à deux niveaux distincts —
     * la route elle-même (405, déjà couvert par PlatformAdminTest) et la
     * permission retirée du wildcard administrateur, vérifiée ici
     * directement sur la configuration des permissions.
     */
    public function test_structures_create_permission_is_removed_from_the_administrateur_wildcard_but_delete_is_kept(): void
    {
        $role = Role::findByName('administrateur', 'sanctum');
        $permissionNames = $role->permissions->pluck('name');

        $this->assertFalse($permissionNames->contains('structures.create'));
        $this->assertTrue($permissionNames->contains('structures.delete'));
    }

    /**
     * §3 : parcours complet via de vrais tokens Bearer (pas actingAs) —
     * création structure+premier admin, mot de passe généré bloqué par
     * must_change_password (423) jusqu'au changement, puis accès normal
     * restauré avec exactement les droits standards d'un administrateur de
     * structure, et isolation confirmée vis-à-vis d'une autre structure
     * existante.
     */
    public function test_full_structure_onboarding_and_forced_password_change_flow_end_to_end(): void
    {
        $platformAdmin = $this->makePlatformAdmin();
        $platformToken = $this->postJson('/api/platform/login', [
            'email' => 'platform-admin@example.test',
            'password' => 'un-mot-de-passe-solide',
        ])->assertOk()->json('token');

        $otherStructure = Structure::factory()->create();

        $creation = $this->withHeader('Authorization', "Bearer {$platformToken}")
            ->postJson('/api/platform/structures', [
                'code' => 'CLN-E2E',
                'legal_name' => 'Clinique Bout en Bout',
                'type' => 'clinique',
                'admin_first_name' => 'Fatoumata',
                'admin_last_name' => 'Sangaré',
                'admin_email' => 'fatoumata.sangare@clinique-e2e.example',
            ]);
        $creation->assertCreated();

        $newStructureId = $creation->json('structure.id');
        $generatedPassword = $creation->json('admin_generated_password');
        $this->assertNotEmpty($generatedPassword);

        // Connexion réelle de l'administrateur nouvellement créé avec le mot
        // de passe généré (guard sanctum, endpoint /auth/login ordinaire).
        $login = $this->postJson('/api/auth/login', [
            'email' => 'fatoumata.sangare@clinique-e2e.example',
            'password' => $generatedPassword,
        ]);
        $login->assertOk();
        $sanctumToken = $login->json('token');
        $this->assertNotEmpty($sanctumToken);

        // Bloqué avant tout changement de mot de passe.
        Auth::forgetGuards();
        $this->withHeader('Authorization', "Bearer {$sanctumToken}")
            ->getJson('/api/patients')
            ->assertStatus(423);

        // Isolation testée aussi dans l'état bloqué : une autre structure
        // existante reste hors de portée (404, jamais une fuite).
        Auth::forgetGuards();
        $this->withHeader('Authorization', "Bearer {$sanctumToken}")
            ->getJson("/api/structures/{$otherStructure->id}")
            ->assertStatus(423); // password_change bloque avant même le 404 — cohérent, ceinture et bretelles.

        Auth::forgetGuards();
        $this->withHeader('Authorization', "Bearer {$sanctumToken}")
            ->postJson('/api/auth/change-password', [
                'password' => 'un-nouveau-mot-de-passe-sur',
                'password_confirmation' => 'un-nouveau-mot-de-passe-sur',
            ])
            ->assertOk();

        // Mot de passe changé, mais 'administrateur' fait partie de
        // User::ROLES_REQUIRING_TWO_FACTOR : le prochain palier est la 2FA
        // obligatoire, exactement comme pour n'importe quel autre
        // administrateur — aucune exemption résiduelle pour ce compte créé
        // via l'administration plateforme. Confirmé ci-dessous en la
        // complétant réellement (même flux que TwoFactorController).
        Auth::forgetGuards();
        $this->withHeader('Authorization', "Bearer {$sanctumToken}")
            ->getJson('/api/patients')
            ->assertStatus(423)
            ->assertJsonFragment(['message' => "Authentification à deux facteurs obligatoire pour ce rôle : activez-la via /auth/2fa/setup avant de continuer."]);

        Auth::forgetGuards();
        $secret = $this->withHeader('Authorization', "Bearer {$sanctumToken}")
            ->postJson('/api/auth/2fa/setup')
            ->assertOk()
            ->json('secret');

        Auth::forgetGuards();
        $code = app(\PragmaRX\Google2FA\Google2FA::class)->getCurrentOtp($secret);
        $this->withHeader('Authorization', "Bearer {$sanctumToken}")
            ->postJson('/api/auth/2fa/confirm', ['code' => $code])
            ->assertOk();

        // Accès normal restauré, une fois les deux paliers (mot de passe
        // puis 2FA) franchis comme pour tout administrateur classique.
        Auth::forgetGuards();
        $this->withHeader('Authorization', "Bearer {$sanctumToken}")
            ->getJson('/api/patients')
            ->assertOk();

        // Droits exactement standards : même ensemble de permissions que le
        // rôle administrateur classique (pas de statut résiduel spécial).
        $createdAdmin = User::query()->where('email', 'fatoumata.sangare@clinique-e2e.example')->firstOrFail();
        $this->assertTrue($createdAdmin->hasRole('administrateur'));
        $this->assertEmpty($createdAdmin->getDirectPermissions(), 'Aucune permission directe résiduelle : tout doit venir du rôle.');
        $expectedPermissions = Role::findByName('administrateur', 'sanctum')->permissions->pluck('name')->sort()->values();
        $actualPermissions = $createdAdmin->getAllPermissions()->pluck('name')->sort()->values();
        $this->assertSame($expectedPermissions->all(), $actualPermissions->all());

        // Isolation : l'autre structure existante reste hors de portée
        // (404), maintenant que le compte a un accès normal fonctionnel.
        Auth::forgetGuards();
        $this->withHeader('Authorization', "Bearer {$sanctumToken}")
            ->getJson("/api/structures/{$otherStructure->id}")
            ->assertStatus(404);

        // Sa propre structure reste accessible.
        Auth::forgetGuards();
        $this->withHeader('Authorization', "Bearer {$sanctumToken}")
            ->getJson("/api/structures/{$newStructureId}")
            ->assertOk();
    }

    /**
     * §4 (critique) : aucune route accessible au guard platform ne permet
     * de consulter une donnée clinique, quelle que soit la structure.
     */
    public function test_platform_guard_cannot_reach_any_clinical_data_endpoint(): void
    {
        $platformAdmin = $this->makePlatformAdmin();
        $token = $this->postJson('/api/platform/login', [
            'email' => 'platform-admin@example.test',
            'password' => 'un-mot-de-passe-solide',
        ])->assertOk()->json('token');

        $structure = Structure::factory()->create();
        $patient = \App\Domain\Patient\Models\Patient::factory()->for($structure)->create();

        $endpoints = [
            ['GET', '/api/patients'],
            ["GET", "/api/patients/{$patient->id}"],
            ['GET', '/api/consultations'],
            ['GET', '/api/invoices'],
            ['GET', "/api/patients/{$patient->id}/timeline"],
            ['GET', '/api/lab-orders'],
            ['GET', '/api/dashboards/medical'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $this->withHeader('Authorization', "Bearer {$token}")
                ->json($method, $uri)
                ->assertUnauthorized();
        }
    }

    /**
     * §5 : activation/désactivation d'un module persistée et relisible via
     * l'endpoint plateforme dédié.
     */
    public function test_module_toggle_is_persisted_and_readable_via_the_platform_endpoint(): void
    {
        $platformAdmin = $this->makePlatformAdmin();
        $token = $this->postJson('/api/platform/login', [
            'email' => 'platform-admin@example.test',
            'password' => 'un-mot-de-passe-solide',
        ])->assertOk()->json('token');

        $creation = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/platform/structures', [
                'code' => 'CLN-MODAUDIT',
                'legal_name' => 'Clinique Modules Audit',
                'type' => 'clinique',
                'admin_first_name' => 'Oumar',
                'admin_last_name' => 'Diallo',
                'admin_email' => 'oumar.diallo@clinique-modaudit.example',
            ]);
        $creation->assertCreated();
        $structureId = $creation->json('structure.id');

        $modulesBefore = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/platform/structures/{$structureId}/modules")
            ->assertOk();
        $module = collect($modulesBefore->json('data'))->firstWhere('module', 'laboratoire');
        $this->assertNotNull($module);
        $this->assertTrue($module['is_active']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/platform/structures/{$structureId}/modules/{$module['id']}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $modulesAfter = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/platform/structures/{$structureId}/modules")
            ->assertOk();
        $moduleAfter = collect($modulesAfter->json('data'))->firstWhere('module', 'laboratoire');
        $this->assertFalse($moduleAfter['is_active']);
        $this->assertNotNull($moduleAfter['deactivated_at']);
    }

    /**
     * §6 : les entrées administration_plateforme sont consultables via
     * l'endpoint d'audit plateforme dédié, ET n'apparaissent PAS via
     * l'endpoint d'audit normal d'une structure (/api/audit-logs), même
     * pour un administrateur de structure qui porte audit.view via son
     * wildcard.
     */
    public function test_platform_audit_entries_are_never_visible_through_the_ordinary_structure_audit_endpoint(): void
    {
        $platformAdmin = $this->makePlatformAdmin();
        $platformToken = $this->postJson('/api/platform/login', [
            'email' => 'platform-admin@example.test',
            'password' => 'un-mot-de-passe-solide',
        ])->assertOk()->json('token');

        $creation = $this->withHeader('Authorization', "Bearer {$platformToken}")
            ->postJson('/api/platform/structures', [
                'code' => 'CLN-AUDITLEAK',
                'legal_name' => 'Clinique Audit Leak',
                'type' => 'clinique',
                'admin_first_name' => 'Aissata',
                'admin_last_name' => 'Bamba',
                'admin_email' => 'aissata.bamba@clinique-auditleak.example',
            ]);
        $creation->assertCreated();
        $structureId = $creation->json('structure.id');
        $generatedPassword = $creation->json('admin_generated_password');

        // Visible via l'endpoint plateforme dédié.
        $platformAudit = $this->withHeader('Authorization', "Bearer {$platformToken}")
            ->getJson("/api/platform/audit-logs?structure_id={$structureId}")
            ->assertOk();
        $this->assertNotEmpty($platformAudit->json('data'));

        // Le nouvel administrateur de la structure change son mot de passe
        // pour obtenir un accès normal (must_change_password), puis
        // consulte SON PROPRE audit de structure.
        $sanctumToken = $this->postJson('/api/auth/login', [
            'email' => 'aissata.bamba@clinique-auditleak.example',
            'password' => $generatedPassword,
        ])->assertOk()->json('token');

        Auth::forgetGuards();
        $this->withHeader('Authorization', "Bearer {$sanctumToken}")
            ->postJson('/api/auth/change-password', [
                'password' => 'un-nouveau-mot-de-passe-sur',
                'password_confirmation' => 'un-nouveau-mot-de-passe-sur',
            ])
            ->assertOk();

        // 'administrateur' exige la 2FA (User::ROLES_REQUIRING_TWO_FACTOR) —
        // même palier à franchir que dans le test de bout en bout ci-dessus
        // avant d'atteindre /api/audit-logs.
        Auth::forgetGuards();
        $secret = $this->withHeader('Authorization', "Bearer {$sanctumToken}")
            ->postJson('/api/auth/2fa/setup')
            ->assertOk()
            ->json('secret');

        Auth::forgetGuards();
        $code = app(\PragmaRX\Google2FA\Google2FA::class)->getCurrentOtp($secret);
        $this->withHeader('Authorization', "Bearer {$sanctumToken}")
            ->postJson('/api/auth/2fa/confirm', ['code' => $code])
            ->assertOk();

        Auth::forgetGuards();
        $structureAudit = $this->withHeader('Authorization', "Bearer {$sanctumToken}")
            ->getJson('/api/audit-logs')
            ->assertOk();

        $logNames = Activity::query()
            ->whereIn('id', collect($structureAudit->json('data'))->pluck('id'))
            ->pluck('log_name');

        $this->assertFalse(
            $logNames->contains('administration_plateforme'),
            "Une entrée administration_plateforme est visible via l'audit normal de structure — fuite d'isolation."
        );
    }
}
