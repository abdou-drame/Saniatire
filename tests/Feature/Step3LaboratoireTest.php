<?php

namespace Tests\Feature;

use App\Domain\Laboratoire\Models\LabOrder;
use App\Domain\Laboratoire\Models\LabResult;
use App\Domain\Laboratoire\Models\LoincCode;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Step3LaboratoireTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Structure $structureB;

    private Site $siteA;

    private User $medecinA;

    private User $secretaireA;

    private User $technicienA;

    private User $biologisteA;

    private Patient $patientA;

    private LoincCode $glycemie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structureA = Structure::factory()->create();
        $this->structureB = Structure::factory()->create();
        $this->siteA = Site::factory()->for($this->structureA)->create();

        $this->medecinA = User::factory()->for($this->structureA)->create();
        $this->medecinA->assignRole('medecin');

        $this->secretaireA = User::factory()->for($this->structureA)->create();
        $this->secretaireA->assignRole('secretaire');

        $this->technicienA = User::factory()->for($this->structureA)->create();
        $this->technicienA->assignRole('technicien_laboratoire');

        $this->biologisteA = User::factory()->for($this->structureA)->create();
        $this->biologisteA->assignRole('biologiste');

        $this->patientA = Patient::factory()->for($this->structureA)->create();

        $this->glycemie = LoincCode::factory()->create(['code' => '2345-7', 'label' => 'Glycémie']);
    }

    private function createOrder(): LabOrder
    {
        $response = $this->actingAs($this->medecinA)->postJson('/api/lab-orders', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'prescriber_id' => $this->medecinA->id,
            'items' => [
                ['loinc_code_id' => $this->glycemie->id],
            ],
        ])->assertCreated();

        return LabOrder::findOrFail($response->json('data.id'));
    }

    // --- Workflow complet ------------------------------------------------

    public function test_full_lab_workflow_from_prescription_to_transmission(): void
    {
        $order = $this->createOrder();
        $item = $order->items()->first();

        $sampleResponse = $this->actingAs($this->technicienA)->postJson("/api/lab-orders/{$order->id}/samples", [
            'barcode' => 'SMP-00000001',
            'sample_type' => 'sang',
        ])->assertCreated();

        $this->assertSame('prelevement_effectue', $order->fresh()->status);
        $this->assertSame('prelevee', $item->fresh()->status);

        $sampleId = $sampleResponse->json('data.id');

        $resultResponse = $this->actingAs($this->technicienA)->postJson("/api/lab-samples/{$sampleId}/results", [
            'lab_order_item_id' => $item->id,
            'value' => '0.95',
            'unit' => 'g/L',
            'reference_min' => 0.7,
            'reference_max' => 1.1,
            'interpretation' => 'normal',
        ])->assertCreated();

        $resultId = $resultResponse->json('data.id');
        $this->assertSame('en_analyse', $order->fresh()->status);

        $this->actingAs($this->technicienA)
            ->patchJson("/api/lab-results/{$resultId}/validate-technique")
            ->assertOk()
            ->assertJsonPath('data.status', 'validation_biologique_attente');

        $this->actingAs($this->biologisteA)
            ->patchJson("/api/lab-results/{$resultId}/validate-biologique")
            ->assertOk()
            ->assertJsonPath('data.status', 'valide');

        $this->assertSame('resultats_disponibles', $order->fresh()->status);

        $this->actingAs($this->biologisteA)
            ->patchJson("/api/lab-results/{$resultId}/transmit")
            ->assertOk()
            ->assertJsonPath('data.status', 'transmis');

        $this->assertSame('transmis', $order->fresh()->status);
    }

    // --- Règle bloquante : double validation ------------------------------

    public function test_biological_validation_is_rejected_before_technical_validation(): void
    {
        $order = $this->createOrder();
        $item = $order->items()->first();

        $sampleId = $this->actingAs($this->technicienA)->postJson("/api/lab-orders/{$order->id}/samples", [
            'barcode' => 'SMP-00000002',
            'sample_type' => 'sang',
        ])->assertCreated()->json('data.id');

        $resultId = $this->actingAs($this->technicienA)->postJson("/api/lab-samples/{$sampleId}/results", [
            'lab_order_item_id' => $item->id,
            'value' => '0.95',
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->biologisteA)
            ->patchJson("/api/lab-results/{$resultId}/validate-biologique")
            ->assertStatus(422);

        $this->assertSame('validation_technique_attente', LabResult::findOrFail($resultId)->status);
    }

    public function test_transmit_is_rejected_before_biological_validation(): void
    {
        $order = $this->createOrder();
        $item = $order->items()->first();

        $sampleId = $this->actingAs($this->technicienA)->postJson("/api/lab-orders/{$order->id}/samples", [
            'barcode' => 'SMP-00000003',
            'sample_type' => 'sang',
        ])->assertCreated()->json('data.id');

        $resultId = $this->actingAs($this->technicienA)->postJson("/api/lab-samples/{$sampleId}/results", [
            'lab_order_item_id' => $item->id,
            'value' => '0.95',
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->technicienA)->patchJson("/api/lab-results/{$resultId}/validate-technique")->assertOk();

        $this->actingAs($this->biologisteA)
            ->patchJson("/api/lab-results/{$resultId}/transmit")
            ->assertStatus(422);
    }

    // --- Permissions par rôle ---------------------------------------------

    public function test_secretary_cannot_create_a_lab_order(): void
    {
        $this->actingAs($this->secretaireA)->postJson('/api/lab-orders', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'prescriber_id' => $this->medecinA->id,
            'items' => [['loinc_code_id' => $this->glycemie->id]],
        ])->assertForbidden();
    }

    public function test_technicien_cannot_validate_biologically(): void
    {
        $order = $this->createOrder();
        $item = $order->items()->first();

        $sampleId = $this->actingAs($this->technicienA)->postJson("/api/lab-orders/{$order->id}/samples", [
            'barcode' => 'SMP-00000004',
            'sample_type' => 'sang',
        ])->assertCreated()->json('data.id');

        $resultId = $this->actingAs($this->technicienA)->postJson("/api/lab-samples/{$sampleId}/results", [
            'lab_order_item_id' => $item->id,
            'value' => '0.95',
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->technicienA)->patchJson("/api/lab-results/{$resultId}/validate-technique")->assertOk();

        $this->actingAs($this->technicienA)
            ->patchJson("/api/lab-results/{$resultId}/validate-biologique")
            ->assertForbidden();
    }

    public function test_biologiste_cannot_validate_technically(): void
    {
        $order = $this->createOrder();
        $item = $order->items()->first();

        $sampleId = $this->actingAs($this->technicienA)->postJson("/api/lab-orders/{$order->id}/samples", [
            'barcode' => 'SMP-00000005',
            'sample_type' => 'sang',
        ])->assertCreated()->json('data.id');

        $resultId = $this->actingAs($this->technicienA)->postJson("/api/lab-samples/{$sampleId}/results", [
            'lab_order_item_id' => $item->id,
            'value' => '0.95',
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->biologisteA)
            ->patchJson("/api/lab-results/{$resultId}/validate-technique")
            ->assertForbidden();
    }

    // --- Régression site courant --------------------------------------------

    public function test_administrateur_without_a_personal_site_can_create_a_lab_order_by_specifying_the_site(): void
    {
        // Même régression que Step3HospitalisationTest / Step3ImagerieTest :
        // un administrateur n'a délibérément aucun site de rattachement
        // personnel. Le backend a toujours accepté un site_id explicite ;
        // seul le frontend bloquait faute de site "par défaut" à proposer.
        $administrateurA = User::factory()->for($this->structureA)->create();
        $administrateurA->assignRole('administrateur');

        $this->assertSame(0, $administrateurA->sites()->count());

        $this->actingAs($administrateurA)->postJson('/api/lab-orders', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'prescriber_id' => $this->medecinA->id,
            'items' => [
                ['loinc_code_id' => $this->glycemie->id],
            ],
        ])->assertCreated()->assertJsonPath('data.site_id', $this->siteA->id);
    }

    // --- Isolation multi-tenant --------------------------------------------

    public function test_a_lab_order_is_invisible_to_another_structure(): void
    {
        $order = $this->createOrder();

        $siteB = Site::factory()->for($this->structureB)->create();
        $medecinB = User::factory()->for($this->structureB)->create();
        $medecinB->assignRole('medecin');

        $this->actingAs($medecinB)->getJson("/api/lab-orders/{$order->id}")->assertNotFound();
    }

    public function test_loinc_codes_referentiel_is_shared_across_structures(): void
    {
        $siteB = Site::factory()->for($this->structureB)->create();
        $medecinB = User::factory()->for($this->structureB)->create();
        $medecinB->assignRole('medecin');

        $this->actingAs($this->medecinA)
            ->getJson("/api/loinc-codes/{$this->glycemie->id}")
            ->assertOk()
            ->assertJsonPath('data.code', '2345-7');

        $this->actingAs($medecinB)
            ->getJson("/api/loinc-codes/{$this->glycemie->id}")
            ->assertOk()
            ->assertJsonPath('data.code', '2345-7');
    }

    // --- Identité des validateurs technique/biologique ------------------------

    /**
     * Régression audit élargi (même bug report que la checklist bloc
     * opératoire) : technical_validated_by/biological_validated_by étaient
     * déjà correctement capturés mais jamais exposés comme nom résolu — ni
     * le contrôleur (eager-load), ni la resource.
     */
    public function test_the_technical_and_biological_validator_identities_are_returned_by_the_api(): void
    {
        $order = $this->createOrder();
        $item = $order->items()->first();

        $sampleId = $this->actingAs($this->technicienA)->postJson("/api/lab-orders/{$order->id}/samples", [
            'barcode' => 'SMP-00000006',
            'sample_type' => 'sang',
        ])->assertCreated()->json('data.id');

        $resultId = $this->actingAs($this->technicienA)->postJson("/api/lab-samples/{$sampleId}/results", [
            'lab_order_item_id' => $item->id,
            'value' => '0.95',
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->technicienA)->patchJson("/api/lab-results/{$resultId}/validate-technique")->assertOk();
        $this->actingAs($this->biologisteA)->patchJson("/api/lab-results/{$resultId}/validate-biologique")->assertOk();

        $data = $this->actingAs($this->medecinA)
            ->getJson("/api/lab-orders/{$order->id}")
            ->assertOk()
            ->json('data');

        $result = collect($data['items'])->firstWhere('id', $item->id)['result'];

        $this->assertSame($this->technicienA->id, $result['technical_validated_by']);
        $this->assertSame(
            trim("{$this->technicienA->first_name} {$this->technicienA->last_name}"),
            $result['technical_validator_label'],
        );
        $this->assertSame('technicien_laboratoire', $result['technical_validator_role']);

        $this->assertSame($this->biologisteA->id, $result['biological_validated_by']);
        $this->assertSame(
            trim("{$this->biologisteA->first_name} {$this->biologisteA->last_name}"),
            $result['biological_validator_label'],
        );
        $this->assertSame('biologiste', $result['biological_validator_role']);
    }
}
