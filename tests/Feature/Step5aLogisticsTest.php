<?php

namespace Tests\Feature;

use App\Domain\Achats\Models\ApprovalRule;
use App\Domain\Achats\Models\Supplier;
use App\Domain\Biomedical\Models\BiomedicalEquipment;
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
}
