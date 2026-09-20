<?php

namespace Tests\Feature;

use App\Domain\Laboratoire\Models\LabOrder;
use App\Domain\Laboratoire\Models\LoincCode;
use App\Domain\Patient\Models\Patient;
use App\Domain\Prescripteur\Models\ExternalPrescriber;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Étape 7b §3/§7 : un prescripteur externe ne voit que les résultats des
 * examens qu'il a lui-même demandés (requester_type/_id), et ne peut créer
 * de demande que pour un patient de sa propre structure cliente.
 */
class PrescriberPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_prescriber_can_create_a_lab_request_for_a_patient_of_their_own_structure(): void
    {
        $structure = Structure::factory()->create();
        $site = Site::factory()->for($structure)->create();
        $prescriber = ExternalPrescriber::factory()->withPortalActivated()->for($structure)->create();
        $patient = Patient::factory()->for($structure)->create();
        $loinc = LoincCode::factory()->create();

        $response = $this->actingAs($prescriber, 'prescriber')->postJson('/api/portail-prescripteur/demandes-labo', [
            'patient_id' => $patient->id,
            'site_id' => $site->id,
            'notes' => 'Bilan de routine',
            'items' => [
                ['loinc_code_id' => $loinc->id],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('lab_orders', [
            'patient_id' => $patient->id,
            'requester_type' => ExternalPrescriber::class,
            'requester_id' => $prescriber->id,
        ]);
    }

    public function test_prescriber_cannot_create_a_request_for_a_patient_of_another_structure(): void
    {
        $structureA = Structure::factory()->create();
        $structureB = Structure::factory()->create();
        $siteA = Site::factory()->for($structureA)->create();
        $prescriber = ExternalPrescriber::factory()->withPortalActivated()->for($structureA)->create();
        $foreignPatient = Patient::factory()->for($structureB)->create();

        $this->actingAs($prescriber, 'prescriber')
            ->postJson('/api/portail-prescripteur/demandes-imagerie', [
                'patient_id' => $foreignPatient->id,
                'site_id' => $siteA->id,
                'exam_type' => 'radio',
            ])
            ->assertNotFound();
    }

    public function test_prescriber_only_sees_transmitted_results_for_requests_they_made_themselves(): void
    {
        $structure = Structure::factory()->create();
        $prescriberA = ExternalPrescriber::factory()->withPortalActivated()->for($structure)->create();
        $prescriberB = ExternalPrescriber::factory()->withPortalActivated()->for($structure)->create();
        $staffUser = User::factory()->for($structure)->create();
        $patient = Patient::factory()->for($structure)->create();

        LabOrder::factory()->for($structure)->create([
            'patient_id' => $patient->id,
            'requester_type' => ExternalPrescriber::class,
            'requester_id' => $prescriberA->id,
            'status' => 'transmis',
        ]);

        // Demande faite par un autre prescripteur externe — ne doit jamais apparaître pour prescriberA.
        LabOrder::factory()->for($structure)->create([
            'patient_id' => $patient->id,
            'requester_type' => ExternalPrescriber::class,
            'requester_id' => $prescriberB->id,
            'status' => 'transmis',
        ]);

        // Demande faite par un membre du personnel — ne doit jamais apparaître non plus.
        LabOrder::factory()->for($structure)->create([
            'patient_id' => $patient->id,
            'requester_type' => User::class,
            'requester_id' => $staffUser->id,
            'status' => 'transmis',
        ]);

        // /resultats a été remplacé par /demandes-labo (toutes statuts, voir
        // PrescriberPortalController::demandesLabo) : même isolation par
        // demandeur, mais le détail clinique reste conditionné à `transmis`.
        $response = $this->actingAs($prescriberA, 'prescriber')->getJson('/api/portail-prescripteur/demandes-labo');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_a_prescriber_token_is_rejected_on_staff_routes(): void
    {
        $structure = Structure::factory()->create();
        $prescriber = ExternalPrescriber::factory()->withPortalActivated()->for($structure)->create();

        $prescriberToken = $prescriber->createToken('prescriber-portal')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$prescriberToken}")
            ->getJson('/api/patients')
            ->assertUnauthorized();
    }

    public function test_a_staff_token_is_rejected_on_the_prescriber_portal(): void
    {
        $structure = Structure::factory()->create();
        $user = User::factory()->for($structure)->create();

        $staffToken = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->getJson('/api/portail-prescripteur/me')
            ->assertUnauthorized();
    }
}
