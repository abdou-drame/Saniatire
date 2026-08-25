<?php

namespace Tests\Feature;

use App\Domain\Assurance\Models\InsuranceConvention;
use App\Domain\Assurance\Models\InsuranceProvider;
use App\Domain\Caisse\Models\Payment;
use App\Domain\Facturation\Models\BillableItem;
use App\Domain\Facturation\Models\Invoice;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardFinancierTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Site $siteA;

    private Site $siteA2;

    private Patient $patientA;

    private User $comptableA;

    private User $infirmierA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structureA = Structure::factory()->create();
        $this->siteA = Site::factory()->for($this->structureA)->create();
        $this->siteA2 = Site::factory()->for($this->structureA)->create();
        $this->patientA = Patient::factory()->for($this->structureA)->create();

        $this->comptableA = User::factory()->for($this->structureA)->create();
        $this->comptableA->assignRole('comptable');

        $this->infirmierA = User::factory()->for($this->structureA)->create();
        $this->infirmierA->assignRole('infirmier');
    }

    // --- Encaissements : total, par site, par mode de paiement -----------

    public function test_encaissements_are_summed_by_site_and_payment_mode(): void
    {
        $from = '2026-08-01';
        $to = '2026-08-31';
        $inPeriod = '2026-08-10 09:00:00';

        $invoiceSiteA = Invoice::factory()->for($this->structureA)->create(['site_id' => $this->siteA->id, 'patient_id' => $this->patientA->id]);
        $invoiceSiteA2 = Invoice::factory()->for($this->structureA)->create(['site_id' => $this->siteA2->id, 'patient_id' => $this->patientA->id]);

        Payment::factory()->for($this->structureA)->create([
            'invoice_id' => $invoiceSiteA->id, 'site_id' => $this->siteA->id,
            'mode_paiement' => 'especes', 'montant' => 10000, 'paid_at' => $inPeriod,
        ]);
        Payment::factory()->for($this->structureA)->create([
            'invoice_id' => $invoiceSiteA->id, 'site_id' => $this->siteA->id,
            'mode_paiement' => 'carte', 'montant' => 5000, 'paid_at' => $inPeriod,
        ]);
        Payment::factory()->for($this->structureA)->create([
            'invoice_id' => $invoiceSiteA2->id, 'site_id' => $this->siteA2->id,
            'mode_paiement' => 'especes', 'montant' => 3000, 'paid_at' => $inPeriod,
        ]);
        // Paiement mobile money échoué : ne doit pas être compté comme encaissement.
        Payment::factory()->for($this->structureA)->create([
            'invoice_id' => $invoiceSiteA->id, 'site_id' => $this->siteA->id,
            'mode_paiement' => 'mobile_money', 'statut_mobile_money' => 'failed', 'montant' => 2000, 'paid_at' => $inPeriod,
        ]);
        // Hors période.
        Payment::factory()->for($this->structureA)->create([
            'invoice_id' => $invoiceSiteA->id, 'site_id' => $this->siteA->id,
            'mode_paiement' => 'especes', 'montant' => 99999, 'paid_at' => '2026-01-01 09:00:00',
        ]);

        $response = $this->actingAs($this->comptableA)
            ->getJson("/api/dashboards/financier?from={$from}&to={$to}")
            ->assertOk();

        // 10000 + 5000 + 3000 = 18000 (le mobile money "failed" et le hors-période sont exclus).
        $this->assertEquals(18000, $response->json('encaissements_total'));

        $parSite = collect($response->json('encaissements_par_site'))->keyBy('site_id');
        $this->assertEquals(15000, $parSite[$this->siteA->id]['total']);
        $this->assertEquals(3000, $parSite[$this->siteA2->id]['total']);

        $parMode = collect($response->json('encaissements_par_mode_paiement'))->keyBy('mode_paiement');
        $this->assertEquals(13000, $parMode['especes']['total']);
        $this->assertEquals(5000, $parMode['carte']['total']);
        $this->assertFalse(isset($parMode['mobile_money']));
    }

    // --- Recettes facturées par prestation --------------------------------

    public function test_recettes_facturees_par_prestation_sums_billable_items(): void
    {
        $from = '2026-08-01';
        $to = '2026-08-31';

        BillableItem::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id, 'categorie' => 'consultation',
            'montant_total' => 5000, 'statut' => 'a_facturer', 'created_at' => '2026-08-05 09:00:00',
        ]);
        BillableItem::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id, 'categorie' => 'consultation',
            'montant_total' => 4000, 'statut' => 'facturee', 'created_at' => '2026-08-05 09:00:00',
        ]);
        BillableItem::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id, 'categorie' => 'laboratoire',
            'montant_total' => 8000, 'statut' => 'a_facturer', 'created_at' => '2026-08-05 09:00:00',
        ]);

        $response = $this->actingAs($this->comptableA)
            ->getJson("/api/dashboards/financier?from={$from}&to={$to}")
            ->assertOk();

        $rows = collect($response->json('recettes_facturees_par_prestation'))->keyBy('categorie');
        $this->assertEquals(9000, $rows['consultation']['montant_total']);
        $this->assertEquals(8000, $rows['laboratoire']['montant_total']);
    }

    // --- Balance âgée : réutilisation directe -----------------------------

    public function test_balance_agee_is_reused_from_the_creances_controller(): void
    {
        Invoice::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id, 'site_id' => $this->siteA->id,
            'date_emission' => now()->subDays(10)->toDateString(),
            'montant_total' => 1000, 'statut' => 'emise',
        ]);

        $response = $this->actingAs($this->comptableA)
            ->getJson('/api/dashboards/financier')
            ->assertOk();

        $this->assertEquals(1000, $response->json('balance_agee.buckets.0-30'));
    }

    // --- Taux de recouvrement ----------------------------------------------

    public function test_taux_recouvrement_divides_encaissements_by_facture(): void
    {
        $from = '2026-08-01';
        $to = '2026-08-31';

        $invoice = Invoice::factory()->for($this->structureA)->create([
            'site_id' => $this->siteA->id, 'patient_id' => $this->patientA->id,
            'date_emission' => '2026-08-05', 'montant_total' => 10000, 'statut' => 'partiellement_payee',
        ]);

        Payment::factory()->for($this->structureA)->create([
            'invoice_id' => $invoice->id, 'site_id' => $this->siteA->id,
            'montant' => 6000, 'paid_at' => '2026-08-06 09:00:00',
        ]);

        $response = $this->actingAs($this->comptableA)
            ->getJson("/api/dashboards/financier?from={$from}&to={$to}")
            ->assertOk();

        // 6000 encaissé / 10000 facturé = 0.6.
        $this->assertEquals(0.6, $response->json('taux_recouvrement'));
    }

    public function test_taux_recouvrement_is_null_when_nothing_was_billed_in_the_period(): void
    {
        $response = $this->actingAs($this->comptableA)
            ->getJson('/api/dashboards/financier?from=2026-08-01&to=2026-08-31')
            ->assertOk();

        $this->assertNull($response->json('taux_recouvrement'));
    }

    // --- Répartition assureur / patient -------------------------------------

    public function test_repartition_assureur_patient_splits_invoice_amounts(): void
    {
        $from = '2026-08-01';
        $to = '2026-08-31';

        $provider = InsuranceProvider::factory()->for($this->structureA)->create(['nom' => 'AssurSanté']);
        $convention = InsuranceConvention::factory()->for($this->structureA)->create(['insurance_provider_id' => $provider->id]);

        Invoice::factory()->for($this->structureA)->create([
            'site_id' => $this->siteA->id, 'patient_id' => $this->patientA->id,
            'insurance_convention_id' => $convention->id,
            'date_emission' => '2026-08-05', 'montant_total' => 1000,
            'montant_part_assurance' => 700, 'montant_part_patient' => 300, 'statut' => 'emise',
        ]);

        Invoice::factory()->for($this->structureA)->create([
            'site_id' => $this->siteA->id, 'patient_id' => $this->patientA->id,
            'insurance_convention_id' => null,
            'date_emission' => '2026-08-06', 'montant_total' => 500,
            'montant_part_assurance' => 0, 'montant_part_patient' => 500, 'statut' => 'emise',
        ]);

        $response = $this->actingAs($this->comptableA)
            ->getJson("/api/dashboards/financier?from={$from}&to={$to}")
            ->assertOk();

        $parAssureur = collect($response->json('repartition_assureur_patient.par_assureur'))->keyBy('nom');
        $this->assertEquals(700, $parAssureur['AssurSanté']['montant']);
        // 300 (facture assurée) + 500 (facture sans assurance) = 800.
        $this->assertEquals(800, $response->json('repartition_assureur_patient.part_patient'));
    }

    // --- Permissions --------------------------------------------------

    public function test_a_nurse_cannot_access_the_financial_dashboard(): void
    {
        $this->actingAs($this->infirmierA)
            ->getJson('/api/dashboards/financier')
            ->assertForbidden();
    }
}
