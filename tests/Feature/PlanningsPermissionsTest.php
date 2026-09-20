<?php

namespace Tests\Feature;

use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Régression pour le correctif "sélecteurs vides sur l'écran Plannings" :
 * les permissions de rôle sont globales (Spatie), pas par structure — ces
 * tests vérifient donc directement les rôles du seeder, valables pour
 * toute structure existante ou future dès que RolePermissionSeeder tourne.
 */
class PlanningsPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_rh_role_can_list_sites_to_populate_the_work_schedule_site_selector(): void
    {
        $structure = Structure::factory()->create();
        $rh = User::factory()->for($structure)->create();
        $rh->assignRole('rh');

        $this->assertTrue($rh->hasPermissionTo('sites.view'));

        $this->actingAs($rh)->getJson('/api/sites')->assertOk();
    }

    public function test_administrateur_role_can_list_the_structures_practitioners(): void
    {
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create();
        $admin->assignRole('administrateur');

        $medecin = User::factory()->for($structure)->create();
        $medecin->assignRole('medecin');

        $this->assertTrue($admin->hasPermissionTo('users.view'));
        $this->assertTrue($admin->hasPermissionTo('appointments.view'));

        $this->actingAs($admin)->getJson('/api/users')
            ->assertOk()
            ->assertJsonFragment(['id' => $medecin->id]);

        $this->actingAs($admin)->getJson('/api/practitioners')
            ->assertOk()
            ->assertJsonFragment(['id' => $medecin->id]);
    }

    /**
     * Bug réel trouvé pendant l'audit complémentaire (pas d'hypothèse de
     * départ) : directeur_medical figure dans RECEPTION_ROLES (App.tsx) et
     * détient achats.view, donc ouvre à la fois le dialogue de prise de
     * rendez-vous et celui de commande d'achat — tous deux avec un
     * sélecteur de site (useSites()) — sans jamais avoir eu sites.view.
     */
    public function test_directeur_medical_role_can_list_sites_for_appointment_and_purchase_order_dialogs(): void
    {
        $structure = Structure::factory()->create();
        $directeurMedical = User::factory()->for($structure)->create();
        $directeurMedical->assignRole('directeur_medical');

        $this->assertTrue($directeurMedical->hasPermissionTo('sites.view'));

        $this->actingAs($directeurMedical)->getJson('/api/sites')->assertOk();
    }

    /**
     * Non-régression pour le rapport "sélecteur de site vide dans Nouvel
     * horaire" : l'investigation a montré qu'il ne s'agissait pas d'un bug
     * (ni de permission ni de code) mais d'une confusion de compte de test
     * entre deux structures distinctes — le compte rh utilisé n'avait tout
     * simplement aucun site dans SA structure, alors que le site vu venait
     * d'une autre structure. Ce test prouve les deux faces du comportement
     * réel : (1) un site créé dans la structure du rh apparaît bien dans
     * /api/sites pour lui (le pipeline de données fonctionne), et (2) le
     * site d'une AUTRE structure n'y apparaît jamais (TenantScope isole
     * correctement) — donc plus jamais confondu avec un bug de permission.
     */
    public function test_rh_role_sees_only_sites_of_its_own_structure_in_the_site_selector(): void
    {
        $ownStructure = Structure::factory()->create();
        $otherStructure = Structure::factory()->create();

        $rh = User::factory()->for($ownStructure)->create();
        $rh->assignRole('rh');

        $ownSite = Site::factory()->for($ownStructure)->create(['name' => 'Site de ma structure']);
        $otherSite = Site::factory()->for($otherStructure)->create(['name' => 'Site d\'une autre structure']);

        $response = $this->actingAs($rh)->getJson('/api/sites')->assertOk();

        $response->assertJsonFragment(['id' => $ownSite->id]);
        $response->assertJsonMissing(['id' => $otherSite->id]);
    }
}
