<?php

namespace Tests\Feature;

use App\Domain\Assurance\Models\InsuranceConvention;
use App\Domain\Assurance\Models\InsuranceProvider;
use App\Domain\Assurance\Models\PatientInsuranceCoverage;
use App\Domain\Caisse\Models\CashSession;
use App\Domain\Caisse\Models\Payment;
use App\Domain\Consultation\Models\Consultation;
use App\Domain\Facturation\Models\BillableItem;
use App\Domain\Facturation\Models\Invoice;
use App\Domain\Facturation\Models\Quote;
use App\Domain\Facturation\Models\ServiceTariff;
use App\Domain\Laboratoire\Models\LabOrder;
use App\Domain\Laboratoire\Models\LoincCode;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Step5bFinanceTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Structure $structureB;

    private Site $siteA;

    private Site $siteB;

    private Patient $patientA;

    private User $medecinA;

    private User $technicienA;

    private User $biologisteA;

    private User $caissierA;

    private User $comptableA;

    private User $caissierB;

    private User $comptableB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structureA = Structure::factory()->create();
        $this->structureB = Structure::factory()->create();
        $this->siteA = Site::factory()->for($this->structureA)->create();
        $this->siteB = Site::factory()->for($this->structureB)->create();
        $this->patientA = Patient::factory()->for($this->structureA)->create();

        $this->medecinA = User::factory()->for($this->structureA)->create();
        $this->medecinA->assignRole('medecin');

        $this->technicienA = User::factory()->for($this->structureA)->create();
        $this->technicienA->assignRole('technicien_laboratoire');

        $this->biologisteA = User::factory()->for($this->structureA)->create();
        $this->biologisteA->assignRole('biologiste');

        $this->caissierA = User::factory()->for($this->structureA)->create();
        $this->caissierA->assignRole('caissier');

        $this->comptableA = User::factory()->for($this->structureA)->create();
        $this->comptableA->assignRole('comptable');

        $this->caissierB = User::factory()->for($this->structureB)->create();
        $this->caissierB->assignRole('caissier');

        $this->comptableB = User::factory()->for($this->structureB)->create();
        $this->comptableB->assignRole('comptable');
    }

    // --- 1. Facturation automatique sur deux formes de module différentes ---

    public function test_completed_prestations_generate_billable_items_across_different_module_shapes(): void
    {
        ServiceTariff::factory()->for($this->structureA)->create([
            'code' => 'CONSULTATION_GENERALE',
            'categorie' => 'consultation',
            'prix_unitaire' => 5000,
            'actif' => true,
        ]);
        ServiceTariff::factory()->for($this->structureA)->create([
            'code' => 'LABORATOIRE_ANALYSE',
            'categorie' => 'laboratoire',
            'prix_unitaire' => 8000,
            'actif' => true,
        ]);

        // Forme 1 : state machine mono-ressource (ConsultationController::close()).
        $consultationId = $this->actingAs($this->medecinA)->postJson('/api/consultations', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA->id,
            'reason' => 'Controle',
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->medecinA)
            ->postJson("/api/consultations/{$consultationId}/close")
            ->assertOk();

        $consultationBillable = BillableItem::where('billable_type', Consultation::class)
            ->where('billable_id', $consultationId)
            ->first();

        $this->assertNotNull($consultationBillable);
        $this->assertSame('consultation', $consultationBillable->categorie);
        $this->assertEquals(5000, (float) $consultationBillable->montant_total);
        $this->assertSame('a_facturer', $consultationBillable->statut);

        // Forme 2 : commande a statuts multiples, etat terminal atteint via
        // LabOrder::syncStatusFromChildren() (transmission du resultat).
        $loinc = LoincCode::factory()->create(['code' => 'GLYC-BILLING', 'label' => 'Glycemie']);

        $orderId = $this->actingAs($this->medecinA)->postJson('/api/lab-orders', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'prescriber_id' => $this->medecinA->id,
            'items' => [['loinc_code_id' => $loinc->id]],
        ])->assertCreated()->json('data.id');

        $order = LabOrder::findOrFail($orderId);
        $item = $order->items()->first();

        $this->assertSame('pending', $order->billing_status);

        $sampleId = $this->actingAs($this->technicienA)->postJson("/api/lab-orders/{$order->id}/samples", [
            'barcode' => 'SMP-BILLING-1',
            'sample_type' => 'sang',
        ])->assertCreated()->json('data.id');

        $resultId = $this->actingAs($this->technicienA)->postJson("/api/lab-samples/{$sampleId}/results", [
            'lab_order_item_id' => $item->id,
            'value' => '0.9',
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->technicienA)->patchJson("/api/lab-results/{$resultId}/validate-technique")->assertOk();
        $this->actingAs($this->biologisteA)->patchJson("/api/lab-results/{$resultId}/validate-biologique")->assertOk();
        $this->actingAs($this->biologisteA)->patchJson("/api/lab-results/{$resultId}/transmit")->assertOk();

        $order->refresh();
        $this->assertSame('transmis', $order->status);
        $this->assertSame('done', $order->billing_status);

        $labBillable = BillableItem::where('billable_type', LabOrder::class)
            ->where('billable_id', $order->id)
            ->first();

        $this->assertNotNull($labBillable);
        $this->assertSame('laboratoire', $labBillable->categorie);
        $this->assertEquals(8000, (float) $labBillable->montant_total);
    }

    // --- 2. Repartition assurance/patient : 3 scenarios chiffres ---

    public function test_insurance_split_covers_simple_rate_capped_rate_and_excluded_category(): void
    {
        $provider = InsuranceProvider::factory()->for($this->structureA)->create();
        $convention = InsuranceConvention::factory()->for($this->structureA)->create([
            'insurance_provider_id' => $provider->id,
            'date_debut' => now()->subYear()->toDateString(),
            'date_fin' => null,
            'actif' => true,
        ]);

        $convention->coverageRules()->create([
            'categorie' => 'consultation',
            'taux_couverture' => 70,
            'plafond_montant' => null,
            'exclu' => false,
        ]);
        $convention->coverageRules()->create([
            'categorie' => 'hospitalisation',
            'taux_couverture' => 80,
            'plafond_montant' => 1000,
            'exclu' => false,
        ]);
        $convention->coverageRules()->create([
            'categorie' => 'imagerie',
            'taux_couverture' => 50,
            'plafond_montant' => null,
            'exclu' => true,
        ]);

        PatientInsuranceCoverage::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'insurance_convention_id' => $convention->id,
            'date_debut' => now()->subYear()->toDateString(),
            'date_fin' => null,
            'actif' => true,
        ]);

        // Scenario A : taux simple, pas de plafond -> 1000 * 70% = 700.
        $itemSimple = BillableItem::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'categorie' => 'consultation',
            'prix_unitaire' => 1000,
            'montant_total' => 1000,
            'statut' => 'a_facturer',
        ]);

        // Scenario B : taux avec plafond depasse -> 5000 * 80% = 4000, plafonne a 1000.
        $itemCapped = BillableItem::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'categorie' => 'hospitalisation',
            'prix_unitaire' => 5000,
            'montant_total' => 5000,
            'statut' => 'a_facturer',
        ]);

        // Scenario C : categorie exclue -> 0 part assurance.
        $itemExcluded = BillableItem::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'categorie' => 'imagerie',
            'prix_unitaire' => 2000,
            'montant_total' => 2000,
            'statut' => 'a_facturer',
        ]);

        $response = $this->actingAs($this->comptableA)->postJson('/api/invoices', [
            'patient_id' => $this->patientA->id,
            'site_id' => $this->siteA->id,
            'billable_item_ids' => [$itemSimple->id, $itemCapped->id, $itemExcluded->id],
        ])->assertCreated();

        $items = collect($response->json('data.items'));

        $simple = $items->firstWhere('categorie', 'consultation');
        $this->assertEquals(700, (float) $simple['montant_assurance']);
        $this->assertEquals(300, (float) $simple['montant_patient']);

        $capped = $items->firstWhere('categorie', 'hospitalisation');
        $this->assertEquals(1000, (float) $capped['montant_assurance']);
        $this->assertEquals(4000, (float) $capped['montant_patient']);

        $excluded = $items->firstWhere('categorie', 'imagerie');
        $this->assertEquals(0, (float) $excluded['montant_assurance']);
        $this->assertEquals(2000, (float) $excluded['montant_patient']);

        $this->assertEquals(8000, (float) $response->json('data.montant_total'));
        $this->assertEquals(1700, (float) $response->json('data.montant_part_assurance'));
        $this->assertEquals(6300, (float) $response->json('data.montant_part_patient'));

        $this->assertSame('facturee', $itemSimple->fresh()->statut);
        $this->assertSame('facturee', $itemCapped->fresh()->statut);
        $this->assertSame('facturee', $itemExcluded->fresh()->statut);
    }

    // --- 3. Paiement especes verrouille par la session de caisse ---

    public function test_cash_payment_requires_an_open_cash_session(): void
    {
        $invoice = Invoice::factory()->for($this->structureA)->create([
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'montant_total' => 10000,
            'statut' => 'emise',
        ]);

        $this->actingAs($this->caissierA)->postJson('/api/payments', [
            'invoice_id' => $invoice->id,
            'site_id' => $this->siteA->id,
            'mode_paiement' => 'especes',
            'montant' => 10000,
        ])->assertStatus(422);

        $sessionId = $this->actingAs($this->caissierA)->postJson('/api/cash-sessions', [
            'site_id' => $this->siteA->id,
            'montant_ouverture' => 20000,
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->caissierA)->postJson('/api/payments', [
            'invoice_id' => $invoice->id,
            'site_id' => $this->siteA->id,
            'mode_paiement' => 'especes',
            'montant' => 10000,
        ])->assertCreated();

        $this->assertSame('payee', $invoice->fresh()->statut);

        $closeResponse = $this->actingAs($this->caissierA)
            ->postJson("/api/cash-sessions/{$sessionId}/close", ['montant_cloture' => 30000])
            ->assertOk();

        // 20000 (ouverture) + 10000 (especes encaissees) = 30000 compte -> ecart nul.
        $this->assertEquals(0, (float) $closeResponse->json('data.ecart'));
        $this->assertSame('fermee', $closeResponse->json('data.statut'));
    }

    // --- 4. Conversion devis -> facture avec recalcul de la repartition ---

    public function test_quote_conversion_to_invoice_recomputes_insurance_split(): void
    {
        $provider = InsuranceProvider::factory()->for($this->structureA)->create();
        $convention = InsuranceConvention::factory()->for($this->structureA)->create([
            'insurance_provider_id' => $provider->id,
            'date_debut' => now()->subYear()->toDateString(),
            'date_fin' => null,
            'actif' => true,
        ]);
        $convention->coverageRules()->create([
            'categorie' => 'consultation',
            'taux_couverture' => 60,
            'plafond_montant' => null,
            'exclu' => false,
        ]);

        $quoteResponse = $this->actingAs($this->comptableA)->postJson('/api/quotes', [
            'patient_id' => $this->patientA->id,
            'site_id' => $this->siteA->id,
            'items' => [
                ['libelle' => 'Consultation specialisee', 'categorie' => 'consultation', 'quantite' => 1, 'prix_unitaire' => 2000],
            ],
        ])->assertCreated();

        $quoteId = $quoteResponse->json('data.id');
        $this->assertEquals(2000, (float) $quoteResponse->json('data.montant_total'));
        $this->assertSame('emis', $quoteResponse->json('data.statut'));

        // La couverture n'existe qu'a partir de maintenant, apres le devis :
        // la conversion doit recalculer la repartition, pas copier le devis.
        PatientInsuranceCoverage::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'insurance_convention_id' => $convention->id,
            'date_debut' => now()->subMonth()->toDateString(),
            'date_fin' => null,
            'actif' => true,
        ]);

        $invoiceResponse = $this->actingAs($this->comptableA)
            ->postJson("/api/quotes/{$quoteId}/convert")
            ->assertCreated();

        $this->assertEquals(2000, (float) $invoiceResponse->json('data.montant_total'));
        $this->assertEquals(1200, (float) $invoiceResponse->json('data.montant_part_assurance'));
        $this->assertEquals(800, (float) $invoiceResponse->json('data.montant_part_patient'));

        $quote = Quote::find($quoteId);
        $this->assertSame('converti', $quote->statut);
        $this->assertEquals($invoiceResponse->json('data.id'), $quote->converted_invoice_id);
    }

    // --- 5. Balance agee : classement par anciennete ---

    public function test_balance_agee_classifies_invoices_by_age_bucket(): void
    {
        Invoice::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'site_id' => $this->siteA->id,
            'date_emission' => now()->subDays(10)->toDateString(),
            'montant_total' => 1000,
            'statut' => 'emise',
        ]);
        Invoice::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'site_id' => $this->siteA->id,
            'date_emission' => now()->subDays(45)->toDateString(),
            'montant_total' => 2000,
            'statut' => 'emise',
        ]);
        Invoice::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'site_id' => $this->siteA->id,
            'date_emission' => now()->subDays(75)->toDateString(),
            'montant_total' => 3000,
            'statut' => 'partiellement_payee',
        ]);
        Invoice::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'site_id' => $this->siteA->id,
            'date_emission' => now()->subDays(120)->toDateString(),
            'montant_total' => 4000,
            'statut' => 'emise',
        ]);
        $invPaid = Invoice::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'site_id' => $this->siteA->id,
            'date_emission' => now()->subDays(50)->toDateString(),
            'montant_total' => 500,
            'statut' => 'payee',
        ]);

        $response = $this->actingAs($this->comptableA)
            ->getJson('/api/creances/balance-agee')
            ->assertOk();

        $buckets = $response->json('buckets');
        $this->assertEquals(1000, (float) $buckets['0-30']);
        $this->assertEquals(2000, (float) $buckets['31-60']);
        $this->assertEquals(3000, (float) $buckets['61-90']);
        $this->assertEquals(4000, (float) $buckets['90+']);

        $invoiceIds = collect($response->json('lignes'))->pluck('invoice_id');
        $this->assertFalse($invoiceIds->contains($invPaid->id));
    }

    // --- 6. Isolation multi-tenant sur les tables financieres ---

    public function test_finance_records_are_invisible_to_another_structure(): void
    {
        $tariff = ServiceTariff::factory()->for($this->structureA)->create();
        $billable = BillableItem::factory()->for($this->structureA)->create(['patient_id' => $this->patientA->id]);
        $provider = InsuranceProvider::factory()->for($this->structureA)->create();
        $convention = InsuranceConvention::factory()->for($this->structureA)->create(['insurance_provider_id' => $provider->id]);
        $coverage = PatientInsuranceCoverage::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'insurance_convention_id' => $convention->id,
        ]);
        $invoice = Invoice::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'site_id' => $this->siteA->id,
        ]);
        $quote = Quote::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'site_id' => $this->siteA->id,
        ]);
        $cashSession = CashSession::factory()->for($this->structureA)->create([
            'site_id' => $this->siteA->id,
            'caissier_id' => $this->caissierA->id,
        ]);
        $payment = Payment::factory()->for($this->structureA)->create([
            'invoice_id' => $invoice->id,
            'site_id' => $this->siteA->id,
            'caissier_id' => $this->caissierA->id,
        ]);

        $this->actingAs($this->comptableB)->getJson("/api/service-tariffs/{$tariff->id}")->assertNotFound();
        $this->actingAs($this->comptableB)->getJson("/api/insurance-providers/{$provider->id}")->assertNotFound();
        $this->actingAs($this->comptableB)->getJson("/api/insurance-conventions/{$convention->id}")->assertNotFound();
        $this->actingAs($this->comptableB)->getJson("/api/patient-insurance-coverages/{$coverage->id}")->assertNotFound();
        $this->actingAs($this->comptableB)->getJson("/api/invoices/{$invoice->id}")->assertNotFound();
        $this->actingAs($this->comptableB)->getJson("/api/quotes/{$quote->id}")->assertNotFound();
        $this->actingAs($this->caissierB)->getJson("/api/cash-sessions/{$cashSession->id}")->assertNotFound();

        $billableIdsForB = collect($this->actingAs($this->comptableB)->getJson('/api/billable-items')->json('data'))->pluck('id');
        $this->assertFalse($billableIdsForB->contains($billable->id));

        $paymentIdsForB = collect($this->actingAs($this->comptableB)->getJson('/api/payments')->json('data'))->pluck('id');
        $this->assertFalse($paymentIdsForB->contains($payment->id));
    }
}
