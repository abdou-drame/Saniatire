<?php

namespace Tests\Feature;

use App\Domain\Imagerie\Models\ImagingOrder;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Step3ImagerieTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Structure $structureB;

    private Site $siteA;

    private User $medecinA;

    private User $secretaireA;

    private User $manipulateurA;

    private User $radiologueA;

    private Patient $patientA;

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

        $this->manipulateurA = User::factory()->for($this->structureA)->create();
        $this->manipulateurA->assignRole('manipulateur_radio');

        $this->radiologueA = User::factory()->for($this->structureA)->create();
        $this->radiologueA->assignRole('radiologue');

        $this->patientA = Patient::factory()->for($this->structureA)->create();
    }

    private function createOrder(): ImagingOrder
    {
        $response = $this->actingAs($this->medecinA)->postJson('/api/imaging-orders', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'prescriber_id' => $this->medecinA->id,
            'exam_type' => 'scanner',
        ])->assertCreated();

        return ImagingOrder::findOrFail($response->json('data.id'));
    }

    private function createStudy(ImagingOrder $order, string $uid = 'STUDY-1', string $accession = 'ACC-1'): array
    {
        $response = $this->actingAs($this->manipulateurA)->postJson("/api/imaging-orders/{$order->id}/studies", [
            'study_instance_uid' => $uid,
            'accession_number' => $accession,
            'modality' => 'CT',
        ])->assertCreated();

        return $response->json('data');
    }

    // --- Workflow complet ------------------------------------------------

    public function test_full_imaging_workflow_from_prescription_to_transmission(): void
    {
        $order = $this->createOrder();
        $study = $this->createStudy($order);

        $this->assertSame('realise', $order->fresh()->status);

        $reportResponse = $this->actingAs($this->radiologueA)->postJson("/api/imaging-studies/{$study['id']}/report", [
            'content' => 'Aucune anomalie décelée.',
        ])->assertCreated();

        $this->assertSame('cr_redige', $order->fresh()->status);

        $reportId = $reportResponse->json('data.id');

        $this->actingAs($this->radiologueA)
            ->patchJson("/api/imaging-reports/{$reportId}/validate")
            ->assertOk()
            ->assertJsonPath('data.status', 'valide');

        $this->assertSame('valide', $order->fresh()->status);

        $this->actingAs($this->radiologueA)
            ->patchJson("/api/imaging-studies/{$study['id']}/transmit")
            ->assertOk()
            ->assertJsonPath('data.status', 'transmis');

        $this->assertSame('transmis', $order->fresh()->status);
    }

    // --- Règle bloquante : CR validé avant transmission --------------------

    public function test_transmit_is_rejected_without_a_report(): void
    {
        $order = $this->createOrder();
        $study = $this->createStudy($order);

        $this->actingAs($this->radiologueA)
            ->patchJson("/api/imaging-studies/{$study['id']}/transmit")
            ->assertStatus(422);
    }

    public function test_transmit_is_rejected_with_an_unvalidated_report(): void
    {
        $order = $this->createOrder();
        $study = $this->createStudy($order);

        $this->actingAs($this->radiologueA)->postJson("/api/imaging-studies/{$study['id']}/report", [
            'content' => 'Compte rendu en cours de rédaction.',
        ])->assertCreated();

        $this->actingAs($this->radiologueA)
            ->patchJson("/api/imaging-studies/{$study['id']}/transmit")
            ->assertStatus(422);
    }

    // --- Permissions par rôle ---------------------------------------------

    public function test_secretary_cannot_create_an_imaging_order(): void
    {
        $this->actingAs($this->secretaireA)->postJson('/api/imaging-orders', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'prescriber_id' => $this->medecinA->id,
            'exam_type' => 'radio',
        ])->assertForbidden();
    }

    public function test_manipulateur_cannot_write_a_report(): void
    {
        $order = $this->createOrder();
        $study = $this->createStudy($order);

        $this->actingAs($this->manipulateurA)->postJson("/api/imaging-studies/{$study['id']}/report", [
            'content' => 'Tentative non autorisée.',
        ])->assertForbidden();
    }

    public function test_manipulateur_cannot_validate_a_report(): void
    {
        $order = $this->createOrder();
        $study = $this->createStudy($order);

        $reportId = $this->actingAs($this->radiologueA)->postJson("/api/imaging-studies/{$study['id']}/report", [
            'content' => 'CR à valider.',
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->manipulateurA)
            ->patchJson("/api/imaging-reports/{$reportId}/validate")
            ->assertForbidden();
    }

    // --- Isolation multi-tenant --------------------------------------------

    public function test_an_imaging_order_is_invisible_to_another_structure(): void
    {
        $order = $this->createOrder();

        $medecinB = User::factory()->for($this->structureB)->create();
        $medecinB->assignRole('medecin');

        $this->actingAs($medecinB)->getJson("/api/imaging-orders/{$order->id}")->assertNotFound();
    }
}
