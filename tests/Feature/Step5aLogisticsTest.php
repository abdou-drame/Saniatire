<?php

namespace Tests\Feature;

use App\Domain\Achats\Models\ApprovalRule;
use App\Domain\Achats\Models\Supplier;
use App\Domain\Biomedical\Models\BiomedicalEquipment;
use App\Domain\Consultation\Models\Consultation;
use App\Domain\Facturation\Models\BillableItem;
use App\Domain\Patient\Models\Patient;
use App\Domain\Pharmacie\Models\Product;
use App\Domain\Pharmacie\Models\ProductBatch;
use App\Domain\Pharmacie\Models\StockThreshold;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Step5aLogisticsTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Structure $structureB;

    private Site $siteA;

    private Site $siteB;

    private User $pharmacienA;

    private User $gestionnaireStockA;

    private User $achatsA;

    private User $directionA;

    private User $gestionnaireStockB;

    private User $achatsB;

    private User $directionB;

    private Product $productA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structureA = Structure::factory()->create();
        $this->structureB = Structure::factory()->create();
        $this->siteA = Site::factory()->for($this->structureA)->create();
        $this->siteB = Site::factory()->for($this->structureB)->create();

        $this->pharmacienA = User::factory()->for($this->structureA)->create();
        $this->pharmacienA->assignRole('pharmacien');

        $this->gestionnaireStockA = User::factory()->for($this->structureA)->create();
        $this->gestionnaireStockA->assignRole('gestionnaire_stock');

        $this->achatsA = User::factory()->for($this->structureA)->create();
        $this->achatsA->assignRole('achats');

        $this->directionA = User::factory()->for($this->structureA)->create();
        $this->directionA->assignRole('direction');

        $this->gestionnaireStockB = User::factory()->for($this->structureB)->create();
        $this->gestionnaireStockB->assignRole('gestionnaire_stock');

        $this->achatsB = User::factory()->for($this->structureB)->create();
        $this->achatsB->assignRole('achats');

        $this->directionB = User::factory()->for($this->structureB)->create();
        $this->directionB->assignRole('direction');

        $this->productA = Product::factory()->for($this->structureA)->create();
    }

    // --- Règle bloquante : lot périmé / à quantité nulle ----------------------

    public function test_an_expired_batch_cannot_be_dispensed(): void
    {
        $batch = ProductBatch::factory()->for($this->structureA)->create([
            'product_id' => $this->productA->id,
            'site_id' => $this->siteA->id,
            'quantite_stock' => 50,
            'date_peremption' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($this->pharmacienA)->postJson('/api/stock-movements', [
            'product_batch_id' => $batch->id,
            'site_id' => $this->siteA->id,
            'type' => 'sortie',
            'quantite' => 5,
        ])->assertStatus(422);

        $this->assertSame(50, $batch->fresh()->quantite_stock);
    }

    public function test_a_zero_quantity_batch_cannot_be_dispensed(): void
    {
        $batch = ProductBatch::factory()->for($this->structureA)->create([
            'product_id' => $this->productA->id,
            'site_id' => $this->siteA->id,
            'quantite_stock' => 0,
            'date_peremption' => now()->addYear()->toDateString(),
        ]);

        $this->actingAs($this->pharmacienA)->postJson('/api/stock-movements', [
            'product_batch_id' => $batch->id,
            'site_id' => $this->siteA->id,
            'type' => 'sortie',
            'quantite' => 1,
        ])->assertStatus(422);
    }

    public function test_a_valid_batch_can_be_dispensed_and_decrements_stock(): void
    {
        $batch = ProductBatch::factory()->for($this->structureA)->create([
            'product_id' => $this->productA->id,
            'site_id' => $this->siteA->id,
            'quantite_stock' => 50,
            'date_peremption' => now()->addYear()->toDateString(),
        ]);

        $this->actingAs($this->pharmacienA)->postJson('/api/stock-movements', [
            'product_batch_id' => $batch->id,
            'site_id' => $this->siteA->id,
            'type' => 'sortie',
            'quantite' => 5,
            'dispensed_for_type' => 'consultation',
            'dispensed_for_id' => 123,
        ])->assertCreated()->assertJsonPath('data.quantite', 5);

        $this->assertSame(45, $batch->fresh()->quantite_stock);
    }

    // --- Alertes de seuil et de péremption ------------------------------------

    public function test_low_threshold_alert_returns_only_products_below_their_configured_minimum(): void
    {
        $productBelow = Product::factory()->for($this->structureA)->create();
        $productAbove = Product::factory()->for($this->structureA)->create();

        StockThreshold::factory()->for($this->structureA)->create([
            'product_id' => $productBelow->id,
            'site_id' => $this->siteA->id,
            'seuil_minimum' => 20,
        ]);
        ProductBatch::factory()->for($this->structureA)->create([
            'product_id' => $productBelow->id,
            'site_id' => $this->siteA->id,
            'quantite_stock' => 10,
        ]);

        StockThreshold::factory()->for($this->structureA)->create([
            'product_id' => $productAbove->id,
            'site_id' => $this->siteA->id,
            'seuil_minimum' => 20,
        ]);
        ProductBatch::factory()->for($this->structureA)->create([
            'product_id' => $productAbove->id,
            'site_id' => $this->siteA->id,
            'quantite_stock' => 30,
        ]);

        $response = $this->actingAs($this->gestionnaireStockA)
            ->getJson('/api/stock/alerts/low-threshold?site_id='.$this->siteA->id)
            ->assertOk();

        $productIds = collect($response->json('data'))->pluck('product_id');

        $this->assertTrue($productIds->contains($productBelow->id));
        $this->assertFalse($productIds->contains($productAbove->id));
    }

    public function test_expiry_alert_returns_only_batches_within_window_or_already_expired(): void
    {
        $expiringSoon = ProductBatch::factory()->for($this->structureA)->create([
            'product_id' => $this->productA->id,
            'site_id' => $this->siteA->id,
            'date_peremption' => now()->addDays(10)->toDateString(),
        ]);
        $alreadyExpired = ProductBatch::factory()->for($this->structureA)->create([
            'product_id' => $this->productA->id,
            'site_id' => $this->siteA->id,
            'date_peremption' => now()->subDays(5)->toDateString(),
        ]);
        $farInFuture = ProductBatch::factory()->for($this->structureA)->create([
            'product_id' => $this->productA->id,
            'site_id' => $this->siteA->id,
            'date_peremption' => now()->addDays(100)->toDateString(),
        ]);

        $response = $this->actingAs($this->gestionnaireStockA)
            ->getJson('/api/stock/alerts/expiry?window=30&site_id='.$this->siteA->id)
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($expiringSoon->id));
        $this->assertTrue($ids->contains($alreadyExpired->id));
        $this->assertFalse($ids->contains($farInFuture->id));
    }

    // --- Circuit d'approbation configurable par structure ---------------------

    private function createOrder(User $as, Structure $structure, Site $site, float $unitPrice, int $quantity): array
    {
        $supplier = Supplier::factory()->for($structure)->create();
        $product = Product::factory()->for($structure)->create();

        return $this->actingAs($as)->postJson('/api/purchase-orders', [
            'site_id' => $site->id,
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantite_commandee' => $quantity, 'prix_unitaire' => $unitPrice],
            ],
        ])->assertCreated()->json('data');
    }

    public function test_approval_circuit_is_driven_by_structure_specific_thresholds_not_hardcoded(): void
    {
        // Structure A : seuil 1000, rôle exigé "gestionnaire_stock".
        ApprovalRule::factory()->for($this->structureA)->create([
            'level' => 1,
            'min_amount' => 1000,
            'role_name' => 'gestionnaire_stock',
        ]);

        // Structure B : seuil différent (200), rôle différent ("direction").
        ApprovalRule::factory()->for($this->structureB)->create([
            'level' => 1,
            'min_amount' => 200,
            'role_name' => 'direction',
        ]);

        // --- Structure A : commande à 1500 (> 1000) déclenche le niveau 1 ---
        $orderA = $this->createOrder($this->achatsA, $this->structureA, $this->siteA, 150, 10);
        $this->assertSame(1500.0, (float) $orderA['montant_total']);

        $orderA = $this->actingAs($this->achatsA)
            ->postJson("/api/purchase-orders/{$orderA['id']}/submit")
            ->assertOk()
            ->json('data');
        $this->assertSame('en_attente_validation', $orderA['statut']);
        $this->assertSame('gestionnaire_stock', $orderA['approvals'][0]['role_name']);

        // La direction a la permission achats.approve mais pas le rôle exigé pour ce niveau.
        $this->actingAs($this->directionA)
            ->postJson("/api/purchase-orders/{$orderA['id']}/approve")
            ->assertStatus(403);

        // Le gestionnaire de stock, lui, détient le rôle exigé par la règle.
        $this->actingAs($this->gestionnaireStockA)
            ->postJson("/api/purchase-orders/{$orderA['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.statut', 'validee');

        // --- Structure A : commande à 500 (< 1000) ne déclenche aucun niveau ---
        $orderALow = $this->createOrder($this->achatsA, $this->structureA, $this->siteA, 50, 10);
        $orderALow = $this->actingAs($this->achatsA)
            ->postJson("/api/purchase-orders/{$orderALow['id']}/submit")
            ->assertOk()
            ->json('data');
        $this->assertSame('validee', $orderALow['statut']);
        $this->assertCount(0, $orderALow['approvals']);

        // --- Structure B : commande à 300 (> 200) déclenche le niveau 1, rôle "direction" ---
        $orderB = $this->createOrder($this->achatsB, $this->structureB, $this->siteB, 30, 10);
        $this->assertSame(300.0, (float) $orderB['montant_total']);

        $orderB = $this->actingAs($this->achatsB)
            ->postJson("/api/purchase-orders/{$orderB['id']}/submit")
            ->assertOk()
            ->json('data');
        $this->assertSame('en_attente_validation', $orderB['statut']);
        $this->assertSame('direction', $orderB['approvals'][0]['role_name']);

        // Le gestionnaire de stock de la structure B n'a pas le rôle "direction" exigé ici.
        $this->actingAs($this->gestionnaireStockB)
            ->postJson("/api/purchase-orders/{$orderB['id']}/approve")
            ->assertStatus(403);

        $this->actingAs($this->directionB)
            ->postJson("/api/purchase-orders/{$orderB['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.statut', 'validee');
    }

    // --- Réception -> mouvement de stock automatique ---------------------------

    public function test_a_conforme_reception_automatically_generates_a_stock_movement(): void
    {
        $order = $this->createOrder($this->achatsA, $this->structureA, $this->siteA, 100, 20);
        $order = $this->actingAs($this->achatsA)
            ->postJson("/api/purchase-orders/{$order['id']}/submit")
            ->assertOk()
            ->json('data');

        // Aucune règle d'approbation configurée pour structureA dans ce test :
        // la commande est donc automatiquement validée à la soumission.
        $this->assertSame('validee', $order['statut']);

        $itemId = $order['items'][0]['id'];

        $this->actingAs($this->gestionnaireStockA)
            ->postJson("/api/purchase-order-items/{$itemId}/receptions", [
                'quantite_recue' => 20,
                'date_reception' => now()->toDateString(),
                'controle_qualite' => 'conforme',
                'numero_lot' => 'LOT-RECEPTION-1',
                'date_peremption' => now()->addYear()->toDateString(),
            ])->assertCreated();

        $batch = ProductBatch::where('numero_lot', 'LOT-RECEPTION-1')->first();
        $this->assertNotNull($batch);
        $this->assertSame(20, $batch->quantite_stock);

        $movement = $batch->movements()->where('type', 'entree')->first();
        $this->assertNotNull($movement);
        $this->assertSame(20, $movement->quantite);

        $this->assertSame('recue_totale', \App\Domain\Achats\Models\PurchaseOrder::find($order['id'])->statut);
    }

    public function test_a_non_conforme_reception_does_not_touch_stock(): void
    {
        $order = $this->createOrder($this->achatsA, $this->structureA, $this->siteA, 100, 20);
        $order = $this->actingAs($this->achatsA)
            ->postJson("/api/purchase-orders/{$order['id']}/submit")
            ->assertOk()
            ->json('data');

        $itemId = $order['items'][0]['id'];

        $this->actingAs($this->gestionnaireStockA)
            ->postJson("/api/purchase-order-items/{$itemId}/receptions", [
                'quantite_recue' => 20,
                'date_reception' => now()->toDateString(),
                'controle_qualite' => 'non_conforme',
                'numero_lot' => 'LOT-REJETE',
                'date_peremption' => now()->addYear()->toDateString(),
            ])->assertCreated();

        $this->assertNull(ProductBatch::where('numero_lot', 'LOT-REJETE')->first());
    }

    // --- Isolation multi-tenant --------------------------------------------

    public function test_a_product_is_invisible_to_another_structure(): void
    {
        $this->actingAs($this->gestionnaireStockB)
            ->getJson("/api/products/{$this->productA->id}")
            ->assertNotFound();
    }

    public function test_a_product_batch_is_invisible_to_another_structure(): void
    {
        $batch = ProductBatch::factory()->for($this->structureA)->create([
            'product_id' => $this->productA->id,
            'site_id' => $this->siteA->id,
        ]);

        $this->actingAs($this->gestionnaireStockB)
            ->getJson("/api/product-batches/{$batch->id}")
            ->assertNotFound();
    }

    // --- Création de produits et de lots (catalogue pharmacie) ----------------

    public function test_a_product_and_its_batch_can_be_created_and_the_batch_appears_in_stock_movement_selection(): void
    {
        $product = $this->actingAs($this->gestionnaireStockA)->postJson('/api/products', [
            'nom_commercial' => 'Doliprane 500mg',
            'dci' => 'Paracétamol',
            'forme_galenique' => 'Comprimé',
            'dosage' => '500mg',
            'categorie' => 'medicament',
            'unite_vente' => 'Boîte',
        ])->assertCreated()->json('data');

        $batch = $this->actingAs($this->gestionnaireStockA)->postJson('/api/product-batches', [
            'product_id' => $product['id'],
            'site_id' => $this->siteA->id,
            'numero_lot' => 'LOT-CATALOGUE-1',
            'date_peremption' => now()->addYear()->toDateString(),
            'quantite_stock' => 100,
            'prix_achat_unitaire' => 250,
        ])->assertCreated()->json('data');

        $batchIds = collect(
            $this->actingAs($this->gestionnaireStockA)
                ->getJson("/api/product-batches?product_id={$product['id']}")
                ->assertOk()
                ->json('data')
        )->pluck('id');
        $this->assertTrue($batchIds->contains($batch['id']));

        $this->actingAs($this->pharmacienA)->postJson('/api/stock-movements', [
            'product_batch_id' => $batch['id'],
            'site_id' => $this->siteA->id,
            'type' => 'entree',
            'quantite' => 20,
        ])->assertCreated();

        $this->assertSame(120, ProductBatch::find($batch['id'])->quantite_stock);
    }

    public function test_a_pharmacien_cannot_create_a_product(): void
    {
        $this->actingAs($this->pharmacienA)->postJson('/api/products', [
            'nom_commercial' => 'Amoxicilline 500mg',
            'dci' => 'Amoxicilline',
            'forme_galenique' => 'Gélule',
            'categorie' => 'medicament',
            'unite_vente' => 'Boîte',
        ])->assertStatus(403);
    }

    public function test_a_product_and_batch_created_by_one_structure_are_isolated_from_another(): void
    {
        $product = $this->actingAs($this->gestionnaireStockA)->postJson('/api/products', [
            'nom_commercial' => 'Ibuprofène 400mg',
            'dci' => 'Ibuprofène',
            'forme_galenique' => 'Comprimé',
            'categorie' => 'medicament',
            'unite_vente' => 'Boîte',
        ])->assertCreated()->json('data');

        $batch = $this->actingAs($this->gestionnaireStockA)->postJson('/api/product-batches', [
            'product_id' => $product['id'],
            'site_id' => $this->siteA->id,
            'numero_lot' => 'LOT-ISOLATION-1',
            'date_peremption' => now()->addYear()->toDateString(),
            'quantite_stock' => 10,
            'prix_achat_unitaire' => 100,
        ])->assertCreated()->json('data');

        $this->actingAs($this->gestionnaireStockB)
            ->getJson("/api/products/{$product['id']}")
            ->assertNotFound();

        $this->actingAs($this->gestionnaireStockB)
            ->getJson("/api/product-batches/{$batch['id']}")
            ->assertNotFound();
    }

    public function test_a_purchase_order_is_invisible_to_another_structure(): void
    {
        $order = $this->createOrder($this->achatsA, $this->structureA, $this->siteA, 100, 10);

        $this->actingAs($this->achatsB)
            ->getJson("/api/purchase-orders/{$order['id']}")
            ->assertNotFound();
    }

    public function test_an_approval_rule_is_invisible_to_another_structure(): void
    {
        $rule = ApprovalRule::factory()->for($this->structureA)->create();

        $this->actingAs($this->directionB)
            ->getJson("/api/approval-rules/{$rule->id}")
            ->assertNotFound();
    }

    public function test_a_biomedical_equipment_is_invisible_to_another_structure(): void
    {
        $equipment = BiomedicalEquipment::factory()->for($this->structureA)->create([
            'site_id' => $this->siteA->id,
        ]);

        $userB = User::factory()->for($this->structureB)->create();
        $userB->assignRole('biomedical');

        $this->actingAs($userB)
            ->getJson("/api/biomedical-equipment/{$equipment->id}")
            ->assertNotFound();
    }

    // --- Maintenance des équipements biomédicaux --------------------------

    public function test_a_maintenance_can_be_created_and_upcoming_overdue_endpoints_distinguish_them(): void
    {
        $biomedicalA = User::factory()->for($this->structureA)->create();
        $biomedicalA->assignRole('biomedical');

        $equipment = BiomedicalEquipment::factory()->for($this->structureA)->create(['site_id' => $this->siteA->id]);

        $this->actingAs($biomedicalA)->postJson('/api/equipment-maintenances', [
            'biomedical_equipment_id' => $equipment->id,
            'type' => 'preventive',
            'date_prevue' => now()->addDays(10)->toDateString(),
        ])->assertCreated();

        $overdue = \App\Domain\Biomedical\Models\EquipmentMaintenance::factory()->for($this->structureA)->create([
            'biomedical_equipment_id' => $equipment->id,
            'type' => 'preventive',
            'date_prevue' => now()->subDays(5)->toDateString(),
            'statut' => 'planifiee',
        ]);

        $upcomingIds = collect($this->actingAs($biomedicalA)->getJson('/api/equipment-maintenances/upcoming')->json('data'))->pluck('id');
        $overdueIds = collect($this->actingAs($biomedicalA)->getJson('/api/equipment-maintenances/overdue')->json('data'))->pluck('id');

        $this->assertFalse($upcomingIds->contains($overdue->id));
        $this->assertTrue($overdueIds->contains($overdue->id));
    }

    public function test_an_equipment_maintenance_is_invisible_to_another_structure(): void
    {
        $equipment = BiomedicalEquipment::factory()->for($this->structureA)->create(['site_id' => $this->siteA->id]);
        $maintenance = \App\Domain\Biomedical\Models\EquipmentMaintenance::factory()->for($this->structureA)->create([
            'biomedical_equipment_id' => $equipment->id,
        ]);

        $userB = User::factory()->for($this->structureB)->create();
        $userB->assignRole('biomedical');

        $this->actingAs($userB)
            ->getJson("/api/equipment-maintenances/{$maintenance->id}")
            ->assertNotFound();
    }

    // --- Grille tarifaire configurable par structure (écran ajouté) -----------

    public function test_a_service_tariff_created_via_api_is_correctly_used_when_billing_a_consultation_and_generating_an_invoice(): void
    {
        $comptableA = User::factory()->for($this->structureA)->create();
        $comptableA->assignRole('comptable');

        $administrateurA = User::factory()->for($this->structureA)->create();
        $administrateurA->assignRole('administrateur');

        $tariff = $this->actingAs($comptableA)->postJson('/api/service-tariffs', [
            'code' => 'CONSULTATION_GENERALE',
            'libelle' => 'Consultation générale',
            'categorie' => 'consultation',
            'prix_unitaire' => 5000,
        ])->assertCreated()->json('data');
        $this->assertTrue($tariff['actif']);

        $patient = Patient::factory()->for($this->structureA)->create();
        $consultation = Consultation::factory()->for($this->structureA)->create([
            'patient_id' => $patient->id,
            'status' => 'en_cours',
        ]);

        // La clôture de la consultation déclenche BillingService::recordService(),
        // qui doit résoudre exactement le tarif créé via l'écran ci-dessus.
        $this->actingAs($administrateurA)
            ->postJson("/api/consultations/{$consultation->id}/close")
            ->assertOk();

        $this->assertDatabaseHas('billable_items', [
            'patient_id' => $patient->id,
            'billable_type' => Consultation::class,
            'billable_id' => $consultation->id,
            'code_prestation' => 'CONSULTATION_GENERALE',
            'prix_unitaire' => 5000,
            'montant_total' => 5000,
            'statut' => 'a_facturer',
        ]);

        $billableItem = BillableItem::where('billable_id', $consultation->id)->firstOrFail();

        $invoice = $this->actingAs($administrateurA)->postJson('/api/invoices', [
            'patient_id' => $patient->id,
            'site_id' => $this->siteA->id,
            'billable_item_ids' => [$billableItem->id],
        ])->assertCreated()->json('data');

        $this->assertSame(5000.0, (float) $invoice['montant_total']);
        $this->assertSame(5000.0, (float) $invoice['montant_part_patient']);
        $this->assertSame(0.0, (float) $invoice['montant_part_assurance']);
    }

    public function test_an_act_closed_without_a_configured_tariff_still_generates_a_billing_line_marked_a_tarifer(): void
    {
        $administrateurA = User::factory()->for($this->structureA)->create();
        $administrateurA->assignRole('administrateur');

        $patient = Patient::factory()->for($this->structureA)->create();
        $consultation = Consultation::factory()->for($this->structureA)->create([
            'patient_id' => $patient->id,
            'status' => 'en_cours',
        ]);

        // Aucun ServiceTariff actif n'existe pour CONSULTATION_GENERALE dans structureA :
        // la clôture ne doit jamais rester silencieuse pour autant.
        $this->actingAs($administrateurA)
            ->postJson("/api/consultations/{$consultation->id}/close")
            ->assertOk();

        $this->assertDatabaseHas('billable_items', [
            'patient_id' => $patient->id,
            'billable_type' => Consultation::class,
            'billable_id' => $consultation->id,
            'code_prestation' => 'CONSULTATION_GENERALE',
            'prix_unitaire' => 0,
            'montant_total' => 0,
            'statut' => 'a_tarifer',
        ]);

        $billableItem = BillableItem::where('billable_id', $consultation->id)->firstOrFail();

        // Une ligne "à tarifer" n'est jamais facturable tant qu'aucun tarif n'existe.
        $this->actingAs($administrateurA)->postJson('/api/invoices', [
            'patient_id' => $patient->id,
            'site_id' => $this->siteA->id,
            'billable_item_ids' => [$billableItem->id],
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'Certaines prestations sont introuvables ou déjà facturées.']);
    }

    public function test_a_caissier_cannot_create_a_service_tariff(): void
    {
        $caissierA = User::factory()->for($this->structureA)->create();
        $caissierA->assignRole('caissier');

        $this->actingAs($caissierA)->postJson('/api/service-tariffs', [
            'code' => 'CONSULTATION_GENERALE',
            'libelle' => 'Consultation générale',
            'categorie' => 'consultation',
            'prix_unitaire' => 5000,
        ])->assertStatus(403);
    }

    public function test_a_service_tariff_created_via_the_api_is_isolated_from_another_structure(): void
    {
        $comptableA = User::factory()->for($this->structureA)->create();
        $comptableA->assignRole('comptable');

        $tariff = $this->actingAs($comptableA)->postJson('/api/service-tariffs', [
            'code' => 'LABORATOIRE_ANALYSE',
            'libelle' => 'Analyse de laboratoire',
            'categorie' => 'laboratoire',
            'prix_unitaire' => 3000,
        ])->assertCreated()->json('data');

        $this->actingAs($this->directionB)
            ->getJson("/api/service-tariffs/{$tariff['id']}")
            ->assertNotFound();
    }

    // --- Règles d'approbation configurables via l'API (écran ajouté) ----------

    public function test_an_approval_rule_created_via_api_is_immediately_enforced_on_purchase_order_validation(): void
    {
        $this->actingAs($this->achatsA)->postJson('/api/approval-rules', [
            'level' => 1,
            'min_amount' => 800,
            'role_name' => 'gestionnaire_stock',
        ])->assertCreated();

        $order = $this->createOrder($this->achatsA, $this->structureA, $this->siteA, 100, 10);
        $this->assertSame(1000.0, (float) $order['montant_total']);

        $order = $this->actingAs($this->achatsA)
            ->postJson("/api/purchase-orders/{$order['id']}/submit")
            ->assertOk()
            ->json('data');
        $this->assertSame('en_attente_validation', $order['statut']);
        $this->assertSame('gestionnaire_stock', $order['approvals'][0]['role_name']);

        $this->actingAs($this->directionA)
            ->postJson("/api/purchase-orders/{$order['id']}/approve")
            ->assertStatus(403);

        $this->actingAs($this->gestionnaireStockA)
            ->postJson("/api/purchase-orders/{$order['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.statut', 'validee');
    }

    public function test_a_direction_user_cannot_create_an_approval_rule(): void
    {
        $this->actingAs($this->directionA)->postJson('/api/approval-rules', [
            'level' => 1,
            'min_amount' => 800,
            'role_name' => 'gestionnaire_stock',
        ])->assertStatus(403);
    }

    public function test_an_approval_rule_created_via_the_api_is_isolated_from_another_structure(): void
    {
        $rule = $this->actingAs($this->achatsA)->postJson('/api/approval-rules', [
            'level' => 1,
            'min_amount' => 800,
            'role_name' => 'gestionnaire_stock',
        ])->assertCreated()->json('data');

        $this->actingAs($this->achatsB)
            ->getJson("/api/approval-rules/{$rule['id']}")
            ->assertNotFound();
    }

    // --- Configuration du seuil d'alerte via l'API (écran manquant corrigé) --------------

    public function test_a_stock_threshold_configured_via_api_makes_a_product_appear_in_the_low_threshold_alert(): void
    {
        $product = Product::factory()->for($this->structureA)->create();
        ProductBatch::factory()->for($this->structureA)->create([
            'product_id' => $product->id,
            'site_id' => $this->siteA->id,
            'quantite_stock' => 6,
        ]);

        $threshold = $this->actingAs($this->gestionnaireStockA)->postJson('/api/stock-thresholds', [
            'product_id' => $product->id,
            'site_id' => $this->siteA->id,
            'seuil_minimum' => 10,
        ])->assertCreated()->json('data');

        $this->assertSame(10, $threshold['seuil_minimum']);

        $alerts = $this->actingAs($this->gestionnaireStockA)
            ->getJson('/api/stock/alerts/low-threshold?site_id='.$this->siteA->id)
            ->assertOk()
            ->json('data');

        $alert = collect($alerts)->firstWhere('product_id', $product->id);
        $this->assertNotNull($alert, 'Le produit sous son seuil doit apparaître dans les alertes.');
        $this->assertSame(10, $alert['seuil_minimum']);
        $this->assertSame(6, $alert['stock_actuel']);

        // Le filtre product_id de GET /stock-thresholds (ajouté pour l'écran de configuration)
        // doit permettre de retrouver ce seuil précis sans le mélanger à ceux d'autres produits.
        $listed = $this->actingAs($this->gestionnaireStockA)
            ->getJson('/api/stock-thresholds?product_id='.$product->id)
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $listed);
        $this->assertSame($threshold['id'], $listed[0]['id']);
    }

    public function test_a_pharmacien_cannot_configure_a_stock_threshold(): void
    {
        $product = Product::factory()->for($this->structureA)->create();

        $this->actingAs($this->pharmacienA)->postJson('/api/stock-thresholds', [
            'product_id' => $product->id,
            'site_id' => $this->siteA->id,
            'seuil_minimum' => 10,
        ])->assertStatus(403);
    }
}
