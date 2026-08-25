<?php

namespace Tests\Feature;

use App\Domain\Caisse\Models\Payment;
use App\Domain\Consultation\Models\Consultation;
use App\Domain\Facturation\Models\Invoice;
use App\Domain\Hospitalisation\Models\Bed;
use App\Domain\Hospitalisation\Models\Ward;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardDirectionTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Structure $structureB;

    private Site $siteA1;

    private Site $siteA2;

    private User $directionA;

    private User $directionB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structureA = Structure::factory()->create();
        $this->structureB = Structure::factory()->create();
        $this->siteA1 = Site::factory()->for($this->structureA)->create(['name' => 'Site A1']);
        $this->siteA2 = Site::factory()->for($this->structureA)->create(['name' => 'Site A2']);

        $this->directionA = User::factory()->for($this->structureA)->create();
        $this->directionA->assignRole('direction');

        $this->directionB = User::factory()->for($this->structureB)->create();
        $this->directionB->assignRole('direction');
    }

    private function invoiceFor(Site $site): Invoice
    {
        $patient = Patient::factory()->for($this->structureA)->create();

        return Invoice::factory()->for($this->structureA)->create(['site_id' => $site->id, 'patient_id' => $patient->id]);
    }

    // --- Comparaison multi-sites + classement -----------------------------

    public function test_multi_site_comparison_ranks_sites_by_revenue(): void
    {
        $from = '2026-08-01';
        $to = '2026-08-31';
        $inPeriod = '2026-08-10 09:00:00';

        $invoiceA1 = $this->invoiceFor($this->siteA1);
        $invoiceA2 = $this->invoiceFor($this->siteA2);

        // Site A1 : 20000 encaissés. Site A2 : 5000 encaissés.
        Payment::factory()->for($this->structureA)->create([
            'invoice_id' => $invoiceA1->id, 'site_id' => $this->siteA1->id, 'montant' => 20000, 'paid_at' => $inPeriod,
        ]);
        Payment::factory()->for($this->structureA)->create([
            'invoice_id' => $invoiceA2->id, 'site_id' => $this->siteA2->id, 'montant' => 5000, 'paid_at' => $inPeriod,
        ]);

        Consultation::factory()->for($this->structureA)->create([
            'patient_id' => $invoiceA1->patient_id, 'practitioner_id' => $this->directionA->id,
            'site_id' => $this->siteA1->id, 'status' => 'terminee', 'created_at' => $inPeriod,
        ]);
        Consultation::factory()->for($this->structureA)->create([
            'patient_id' => $invoiceA2->patient_id, 'practitioner_id' => $this->directionA->id,
            'site_id' => $this->siteA2->id, 'status' => 'terminee', 'created_at' => $inPeriod,
        ]);

        $response = $this->actingAs($this->directionA)
            ->getJson("/api/dashboards/direction?from={$from}&to={$to}")
            ->assertOk();

        $comparaison = $response->json('comparaison_sites');
        $this->assertCount(2, $comparaison);

        // Trié par CA décroissant : Site A1 (20000) en rang 1.
        $this->assertEquals($this->siteA1->id, $comparaison[0]['site_id']);
        $this->assertEquals(20000, $comparaison[0]['ca']);
        $this->assertEquals(1, $comparaison[0]['rang']);
        $this->assertEquals(1, $comparaison[0]['nombre_patients']);

        $this->assertEquals($this->siteA2->id, $comparaison[1]['site_id']);
        $this->assertEquals(5000, $comparaison[1]['ca']);
        $this->assertEquals(2, $comparaison[1]['rang']);

        // Consolidé = somme des deux sites.
        $this->assertEquals(25000, $response->json('consolide.ca_total'));
        $this->assertEquals(2, $response->json('consolide.nombre_patients'));
    }

    public function test_occupancy_rate_is_computed_per_site_and_averaged_for_the_consolidated_view(): void
    {
        $wardA1 = Ward::factory()->for($this->structureA)->create(['site_id' => $this->siteA1->id]);
        Bed::factory()->for($this->structureA)->count(2)->create(['site_id' => $this->siteA1->id, 'ward_id' => $wardA1->id, 'status' => 'occupe']);
        Bed::factory()->for($this->structureA)->count(2)->create(['site_id' => $this->siteA1->id, 'ward_id' => $wardA1->id, 'status' => 'libre']);
        // Site A1 : 2/4 = 50%.

        $wardA2 = Ward::factory()->for($this->structureA)->create(['site_id' => $this->siteA2->id]);
        Bed::factory()->for($this->structureA)->count(1)->create(['site_id' => $this->siteA2->id, 'ward_id' => $wardA2->id, 'status' => 'occupe']);
        Bed::factory()->for($this->structureA)->count(9)->create(['site_id' => $this->siteA2->id, 'ward_id' => $wardA2->id, 'status' => 'libre']);
        // Site A2 : 1/10 = 10%.

        $response = $this->actingAs($this->directionA)
            ->getJson('/api/dashboards/direction')
            ->assertOk();

        $comparaison = collect($response->json('comparaison_sites'))->keyBy('site_id');
        $this->assertEquals(50.0, $comparaison[$this->siteA1->id]['taux_occupation']);
        $this->assertEquals(10.0, $comparaison[$this->siteA2->id]['taux_occupation']);

        // Moyenne simple des taux par ward (pas pondérée par le nombre de lits) : (50 + 10) / 2 = 30.
        $this->assertEquals(30.0, $response->json('consolide.taux_occupation_moyen'));
    }

    // --- Isolation : la direction de la structure B ne voit jamais A ------

    public function test_a_group_direction_user_never_sees_another_structures_sites(): void
    {
        $siteB = Site::factory()->for($this->structureB)->create();

        $response = $this->actingAs($this->directionB)
            ->getJson('/api/dashboards/direction')
            ->assertOk();

        $siteIds = collect($response->json('comparaison_sites'))->pluck('site_id');
        $this->assertTrue($siteIds->contains($siteB->id));
        $this->assertFalse($siteIds->contains($this->siteA1->id));
        $this->assertFalse($siteIds->contains($this->siteA2->id));
    }

    // --- Cache : filet de sécurité, invalidable via Cache::forget --------

    public function test_the_consolidated_view_is_cached_until_the_cache_key_is_forgotten(): void
    {
        $from = '2026-08-01';
        $to = '2026-08-31';

        $invoiceA1 = $this->invoiceFor($this->siteA1);
        Payment::factory()->for($this->structureA)->create([
            'invoice_id' => $invoiceA1->id, 'site_id' => $this->siteA1->id, 'montant' => 1000, 'paid_at' => '2026-08-10 09:00:00',
        ]);

        $first = $this->actingAs($this->directionA)
            ->getJson("/api/dashboards/direction?from={$from}&to={$to}")
            ->assertOk();
        $this->assertEquals(1000, $first->json('consolide.ca_total'));

        Payment::factory()->for($this->structureA)->create([
            'invoice_id' => $invoiceA1->id, 'site_id' => $this->siteA1->id, 'montant' => 500, 'paid_at' => '2026-08-11 09:00:00',
        ]);

        $stillCached = $this->actingAs($this->directionA)
            ->getJson("/api/dashboards/direction?from={$from}&to={$to}")
            ->assertOk();
        $this->assertEquals(1000, $stillCached->json('consolide.ca_total'));

        $cacheKey = sprintf('dashboard.direction.%s.%s.%s', $this->structureA->id, $from, $to);
        Cache::forget($cacheKey);

        $afterInvalidation = $this->actingAs($this->directionA)
            ->getJson("/api/dashboards/direction?from={$from}&to={$to}")
            ->assertOk();
        $this->assertEquals(1500, $afterInvalidation->json('consolide.ca_total'));
    }

    // --- Permissions --------------------------------------------------

    public function test_a_doctor_without_direction_role_cannot_access_the_group_dashboard(): void
    {
        $medecinA = User::factory()->for($this->structureA)->create();
        $medecinA->assignRole('medecin');

        $this->actingAs($medecinA)
            ->getJson('/api/dashboards/direction')
            ->assertForbidden();
    }
}
