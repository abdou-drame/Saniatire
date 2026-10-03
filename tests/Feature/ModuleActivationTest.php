<?php

namespace Tests\Feature;

use App\Domain\Patient\Models\Patient;
use App\Domain\Platform\ModuleCatalog;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Prescripteur\Models\ExternalPrescriber;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\Structure\Models\StructureModule;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Centre de contrôle, livraison B : modules réellement actifs. Règle unique
 * dans ModuleCatalog (socle toujours actif, premium actif sauf ligne
 * structure_modules désactivée), appliquée côté backend par le middleware
 * `module:<clé>`, exposée par /auth/me, pilotée par la plateforme.
 * Un jeton par appel, à cause du cache de guard Sanctum documenté dans
 * PlatformAdministrationAuditTest.
 */
class ModuleActivationTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->structure = Structure::factory()->create();
    }

    private function staffToken(?Structure $structure = null): string
    {
        $user = User::factory()->for($structure ?? $this->structure)->create();
        $user->assignRole('administrateur');

        return $user->createToken('api')->plainTextToken;
    }

    private function platformAdmin(): PlatformAdmin
    {
        return PlatformAdmin::create([
            'name' => 'Admin Plateforme',
            'email' => 'platform-admin@example.test',
            'password' => Hash::make('un-mot-de-passe-solide'),
        ]);
    }

    private function disable(string $module, ?Structure $structure = null): void
    {
        StructureModule::updateOrCreate(
            ['structure_id' => ($structure ?? $this->structure)->id, 'module' => $module],
            ['is_active' => false, 'deactivated_at' => now()],
        );
    }

    private function asToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    public function test_a_premium_module_without_any_row_is_active(): void
    {
        $this->assertSame(0, StructureModule::where('structure_id', $this->structure->id)->count());
        $this->assertTrue(ModuleCatalog::isActiveFor($this->structure->id, 'laboratoire'));

        $this->asToken($this->staffToken())->getJson('/api/lab-orders')->assertOk();
    }

    public function test_a_disabled_module_refuses_reads_and_writes_with_a_clear_message(): void
    {
        $this->disable('laboratoire');
        $message = ModuleCatalog::inactiveMessage('laboratoire');

        $this->asToken($this->staffToken())->getJson('/api/lab-orders')
            ->assertStatus(403)->assertJsonPath('message', $message);
        $this->asToken($this->staffToken())->postJson('/api/lab-orders', [])
            ->assertStatus(403)->assertJsonPath('message', $message);

        // Les autres modules et le socle ne sont pas touchés.
        $this->asToken($this->staffToken())->getJson('/api/imaging-orders')->assertOk();
        $this->asToken($this->staffToken())->getJson('/api/patients')->assertOk();
    }

    public function test_commercial_modules_cover_their_whole_route_family(): void
    {
        $this->disable('finance_avancee');
        $this->disable('rh');

        $this->asToken($this->staffToken())->getJson('/api/creances/balance-agee')->assertStatus(403);
        $this->asToken($this->staffToken())->getJson('/api/leave-requests')->assertStatus(403);
        $this->asToken($this->staffToken())->getJson('/api/on-call')->assertStatus(403);

        // Audit et tableaux de bord : jamais des modules.
        $this->asToken($this->staffToken())->getJson('/api/audit-logs')->assertOk();
    }

    public function test_auth_me_lists_the_active_modules(): void
    {
        $this->disable('dentaire');

        $modules = $this->asToken($this->staffToken())->getJson('/api/auth/me')->assertOk()->json('data.modules');

        $this->assertContains('queue', $modules);
        $this->assertContains('icd', $modules);
        $this->assertContains('laboratoire', $modules);
        $this->assertNotContains('dentaire', $modules);
    }

    public function test_the_platform_lists_the_full_catalogue_with_core_modules_flagged(): void
    {
        $this->disable('stock');

        $data = collect($this->actingAs($this->platformAdmin(), 'platform')
            ->getJson("/api/platform/structures/{$this->structure->id}/modules")
            ->assertOk()->json('data'))->keyBy('module');

        $this->assertCount(count(ModuleCatalog::CORE) + count(ModuleCatalog::PREMIUM), $data);
        $this->assertTrue($data['consultations']['is_core']);
        $this->assertTrue($data['consultations']['is_active']);
        $this->assertFalse($data['stock']['is_core']);
        $this->assertFalse($data['stock']['is_active']);
        $this->assertTrue($data['imagerie']['is_active']);
        $this->assertSame('Pharmacie et stocks', $data['stock']['label']);
    }

    public function test_core_modules_cannot_be_deactivated_and_unknown_keys_are_404(): void
    {
        $admin = $this->platformAdmin();

        foreach (['consultations', 'queue', 'icd', 'facturation'] as $core) {
            $this->actingAs($admin, 'platform')
                ->patchJson("/api/platform/structures/{$this->structure->id}/modules/{$core}", ['is_active' => false])
                ->assertStatus(422);
        }

        $this->actingAs($admin, 'platform')
            ->patchJson("/api/platform/structures/{$this->structure->id}/modules/audit", ['is_active' => false])
            ->assertNotFound();

        $this->assertSame(0, StructureModule::where('structure_id', $this->structure->id)->count());
        $this->assertSame(array_keys(ModuleCatalog::CORE), array_values(array_intersect(
            ModuleCatalog::activeFor($this->structure->id), array_keys(ModuleCatalog::CORE)
        )));
    }

    public function test_the_platform_toggles_a_premium_module_and_it_is_journaled(): void
    {
        $admin = $this->platformAdmin();
        $uri = "/api/platform/structures/{$this->structure->id}/modules/laboratoire";

        $this->actingAs($admin, 'platform')->patchJson($uri, ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertFalse(ModuleCatalog::isActiveFor($this->structure->id, 'laboratoire'));

        $this->actingAs($admin, 'platform')->patchJson($uri, ['is_active' => true])
            ->assertOk()->assertJsonPath('data.is_active', true);
        $this->assertTrue(ModuleCatalog::isActiveFor($this->structure->id, 'laboratoire'));

        $actions = Activity::query()->where('log_name', 'administration_plateforme')
            ->where('structure_id', $this->structure->id)->orderBy('id')->get();
        $this->assertSame(['desactivation_module', 'activation_module'], $actions->pluck('properties.action')->all());
        $this->assertSame(['laboratoire', 'laboratoire'], $actions->pluck('properties.module')->all());
        $this->assertSame($admin->id, (int) $actions->first()->causer_id);
    }

    public function test_deactivation_on_one_structure_does_not_affect_another(): void
    {
        $other = Structure::factory()->create();
        $this->disable('laboratoire');

        $this->asToken($this->staffToken($other))->getJson('/api/lab-orders')->assertOk();
        $this->assertContains('laboratoire', ModuleCatalog::activeFor($other->id));
    }

    public function test_prescriber_login_is_refused_when_the_prescripteurs_module_is_off(): void
    {
        $prescriber = ExternalPrescriber::factory()->withPortalActivated()->for($this->structure)->create();
        $this->disable('prescripteurs');

        $this->postJson('/api/portail-prescripteur/login', ['email' => $prescriber->email, 'password' => 'password'])
            ->assertStatus(403)
            ->assertJsonPath('message', ModuleCatalog::inactiveMessage('prescripteurs'))
            ->assertJsonMissingPath('token');

        // Le personnel perd aussi l'écran des prescripteurs externes.
        $this->asToken($this->staffToken())->getJson('/api/external-prescribers')->assertStatus(403);
    }

    public function test_an_existing_prescriber_token_gets_a_401_with_the_reason_then_works_again(): void
    {
        $token = ExternalPrescriber::factory()->withPortalActivated()->for($this->structure)->create()
            ->createToken('prescriber-portal')->plainTextToken;

        $this->asToken($token)->getJson('/api/portail-prescripteur/me')->assertOk();

        $this->disable('prescripteurs');
        $this->asToken($token)->getJson('/api/portail-prescripteur/me')
            ->assertStatus(401)
            ->assertJsonPath('message', ModuleCatalog::inactiveMessage('prescripteurs'));
        $this->asToken($token)->getJson('/api/portail-prescripteur/patients')->assertStatus(401);

        // Pas de révocation : la réactivation rend l'accès au même jeton.
        StructureModule::where('structure_id', $this->structure->id)->where('module', 'prescripteurs')->update(['is_active' => true]);
        $this->asToken($token)->getJson('/api/portail-prescripteur/me')->assertOk();

        $this->disable('prescripteurs');
        // La déconnexion reste possible.
        $this->asToken($token)->postJson('/api/portail-prescripteur/logout')->assertOk();
    }

    public function test_prescriber_lab_and_imaging_requests_follow_their_own_modules(): void
    {
        $prescriber = ExternalPrescriber::factory()->withPortalActivated()->for($this->structure)->create();
        $this->disable('laboratoire');

        $this->actingAs($prescriber, 'prescriber')->getJson('/api/portail-prescripteur/demandes-labo')
            ->assertStatus(403)
            ->assertJsonPath('message', ModuleCatalog::inactiveMessage('laboratoire'));
        $this->actingAs($prescriber, 'prescriber')->getJson('/api/portail-prescripteur/demandes-imagerie')->assertOk();
        $this->actingAs($prescriber, 'prescriber')->getJson('/api/portail-prescripteur/me')->assertOk();
    }

    public function test_patient_portal_complaints_follow_the_reclamations_module(): void
    {
        $patient = Patient::factory()->withPortalActivated()->for($this->structure)->create();

        $this->actingAs($patient, 'patient')->getJson('/api/portail-patient/reclamations')->assertOk();

        $this->disable('reclamations');
        $this->actingAs($patient, 'patient')->getJson('/api/portail-patient/reclamations')
            ->assertStatus(403)
            ->assertJsonPath('message', ModuleCatalog::inactiveMessage('reclamations'));
        $this->actingAs($patient, 'patient')->getJson('/api/portail-patient/me')->assertOk();
    }

    public function test_both_portals_expose_the_active_modules_at_login_and_on_me(): void
    {
        $patient = Patient::factory()->withPortalActivated()->for($this->structure)->create();
        $prescriber = ExternalPrescriber::factory()->withPortalActivated()->for($this->structure)->create();
        $this->disable('reclamations');
        $this->disable('imagerie');

        $patientLogin = $this->postJson('/api/portail-patient/login', ['email' => $patient->email, 'password' => 'password'])
            ->assertOk()->json('patient.modules');
        $this->assertNotContains('reclamations', $patientLogin);
        $this->assertContains('laboratoire', $patientLogin);
        $this->assertNotContains('reclamations', $this->actingAs($patient, 'patient')
            ->getJson('/api/portail-patient/me')->assertOk()->json('data.modules'));

        $prescriberLogin = $this->postJson('/api/portail-prescripteur/login', ['email' => $prescriber->email, 'password' => 'password'])
            ->assertOk()->json('prescriber.modules');
        $this->assertNotContains('imagerie', $prescriberLogin);
        $this->assertContains('laboratoire', $prescriberLogin);
        $this->assertNotContains('imagerie', $this->actingAs($prescriber, 'prescriber')
            ->getJson('/api/portail-prescripteur/me')->assertOk()->json('data.modules'));
    }

    public function test_a_second_active_site_requires_the_multi_sites_module(): void
    {
        $this->disable('multi_sites');
        $first = Site::factory()->for($this->structure)->create(['is_active' => true]);

        $this->asToken($this->staffToken())->postJson('/api/sites', ['name' => 'Annexe'])
            ->assertStatus(403)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Multi-sites'));

        // Un site inactif reste créable, mais pas réactivable.
        $inactiveId = $this->asToken($this->staffToken())->postJson('/api/sites', ['name' => 'Annexe', 'is_active' => false])
            ->assertCreated()->json('data.id');
        $this->asToken($this->staffToken())->putJson("/api/sites/{$inactiveId}", ['name' => 'Annexe', 'is_active' => true])
            ->assertStatus(403);

        // Modifier le site actif existant reste possible.
        $this->asToken($this->staffToken())->putJson("/api/sites/{$first->id}", ['name' => 'Siège rénové', 'is_active' => true])
            ->assertOk();

        // Les sites d'une autre structure ne comptent pas.
        $other = Structure::factory()->create();
        $this->disable('multi_sites', $other);
        $this->asToken($this->staffToken($other))->postJson('/api/sites', ['name' => 'Premier site'])->assertCreated();
    }

    public function test_with_the_multi_sites_module_several_active_sites_are_allowed(): void
    {
        Site::factory()->for($this->structure)->create(['is_active' => true]);

        $this->asToken($this->staffToken())->postJson('/api/sites', ['name' => 'Annexe'])->assertCreated();
        $this->assertSame(2, Site::withoutGlobalScopes()->where('structure_id', $this->structure->id)->where('is_active', true)->count());
    }

    public function test_existing_assurance_rows_are_carried_over_to_finance_avancee(): void
    {
        $migration = require database_path('migrations/2026_10_03_000002_create_finance_avancee_structure_modules.php');
        $other = Structure::factory()->create();

        StructureModule::create(['structure_id' => $this->structure->id, 'module' => 'assurance', 'is_active' => false]);
        StructureModule::create(['structure_id' => $other->id, 'module' => 'assurance', 'is_active' => false]);
        StructureModule::create(['structure_id' => $other->id, 'module' => 'finance_avancee', 'is_active' => true]);

        $migration->up();

        $this->assertFalse(ModuleCatalog::isActiveFor($this->structure->id, 'finance_avancee'));
        // Une ligne finance_avancee déjà présente n'est jamais écrasée.
        $this->assertTrue(ModuleCatalog::isActiveFor($other->id, 'finance_avancee'));
        $this->assertSame(1, StructureModule::where('structure_id', $other->id)->where('module', 'finance_avancee')->count());
        // Rien n'est supprimé.
        $this->assertSame(2, StructureModule::where('module', 'assurance')->count());
    }
}
