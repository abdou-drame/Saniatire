<?php

namespace Tests\Feature;

use App\Domain\Patient\Models\Patient;
use App\Domain\Qualite\Models\Complaint;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientPortalComplaintTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Patient $patientA;

    private Patient $otherPatientA;

    private User $secretaireA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structureA = Structure::factory()->create();
        $this->patientA = Patient::factory()->withPortalActivated()->for($this->structureA)->create();
        $this->otherPatientA = Patient::factory()->withPortalActivated()->for($this->structureA)->create();

        $this->secretaireA = User::factory()->for($this->structureA)->create();
        $this->secretaireA->assignRole('secretaire');
    }

    public function test_a_patient_can_submit_a_complaint_that_appears_staff_side_with_the_correct_patient(): void
    {
        $created = $this->actingAs($this->patientA, 'patient')->postJson('/api/portail-patient/reclamations', [
            'motif' => 'attente',
            'description' => "Temps d'attente trop long en salle.",
            'service_concerne' => 'consultation',
        ])->assertCreated();

        $created->assertJsonPath('data.patient_id', $this->patientA->id);
        $created->assertJsonPath('data.origin', 'patient');
        $created->assertJsonPath('data.statut', 'ouverte');

        $complaintId = $created->json('data.id');

        // Visible côté personnel, dans l'écran existant, sans distinction de périmètre.
        // Une réclamation soumise par le patient n'a pas de gestionnaire assigné :
        // reclamations.manage_all est nécessaire pour la voir dans l'index (même
        // périmètre "own scope" qu'une réclamation créée par le personnel non assignée).
        $this->secretaireA->givePermissionTo('reclamations.manage_all');

        $staffView = $this->actingAs($this->secretaireA, 'sanctum')
            ->getJson('/api/complaints')
            ->assertOk();

        $this->assertTrue(collect($staffView->json('data'))->contains(fn ($row) => $row['id'] === $complaintId));

        $staffShow = $this->actingAs($this->secretaireA, 'sanctum')
            ->getJson("/api/complaints/{$complaintId}")
            ->assertOk();
        $this->assertSame($this->patientA->id, $staffShow->json('data.patient_id'));
        $this->assertSame('patient', $staffShow->json('data.origin'));
    }

    public function test_a_patient_sees_only_their_own_complaints_never_anothers(): void
    {
        $mine = Complaint::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
        ]);
        $notMine = Complaint::factory()->for($this->structureA)->create([
            'patient_id' => $this->otherPatientA->id,
        ]);

        $list = $this->actingAs($this->patientA, 'patient')
            ->getJson('/api/portail-patient/reclamations')
            ->assertOk();

        $ids = collect($list->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($notMine->id));

        $this->actingAs($this->patientA, 'patient')
            ->getJson("/api/portail-patient/reclamations/{$notMine->id}")
            ->assertNotFound();

        $this->actingAs($this->patientA, 'patient')
            ->getJson("/api/portail-patient/reclamations/{$mine->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $mine->id);
    }

    public function test_a_patient_never_sees_internal_only_responses(): void
    {
        $complaint = Complaint::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'gestionnaire_id' => $this->secretaireA->id,
            'statut' => 'en_cours',
        ]);

        $this->actingAs($this->secretaireA, 'sanctum')->postJson("/api/complaints/{$complaint->id}/respond", [
            'message' => 'Réponse visible par le patient.',
            'visible_patient' => true,
        ])->assertCreated();

        $this->actingAs($this->secretaireA, 'sanctum')->postJson("/api/complaints/{$complaint->id}/respond", [
            'message' => 'Note interne réservée au personnel.',
            'visible_patient' => false,
        ])->assertCreated();

        $patientView = $this->actingAs($this->patientA, 'patient')
            ->getJson("/api/portail-patient/reclamations/{$complaint->id}")
            ->assertOk();

        $messages = collect($patientView->json('data.responses'))->pluck('message');
        $this->assertTrue($messages->contains('Réponse visible par le patient.'));
        $this->assertFalse($messages->contains('Note interne réservée au personnel.'));

        // Côté personnel, les deux réponses restent visibles.
        $staffView = $this->actingAs($this->secretaireA, 'sanctum')
            ->getJson("/api/complaints/{$complaint->id}")
            ->assertOk();
        $this->assertCount(2, $staffView->json('data.responses'));
    }

    public function test_a_complaint_from_one_structure_is_never_visible_from_another(): void
    {
        $structureB = Structure::factory()->create();
        $patientB = Patient::factory()->withPortalActivated()->for($structureB)->create();

        $complaintA = Complaint::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
        ]);

        // Un patient d'une autre structure ne peut jamais voir la réclamation, même par id direct.
        $this->actingAs($patientB, 'patient')
            ->getJson("/api/portail-patient/reclamations/{$complaintA->id}")
            ->assertNotFound();

        $listB = $this->actingAs($patientB, 'patient')
            ->getJson('/api/portail-patient/reclamations')
            ->assertOk();
        $this->assertEmpty($listB->json('data'));

        // Un membre du personnel d'une autre structure ne la voit pas non plus.
        $secretaireB = User::factory()->for($structureB)->create();
        $secretaireB->assignRole('secretaire');
        $secretaireB->givePermissionTo('reclamations.manage_all');

        $staffListB = $this->actingAs($secretaireB, 'sanctum')
            ->getJson('/api/complaints')
            ->assertOk();
        $this->assertEmpty($staffListB->json('data'));

        $this->actingAs($secretaireB, 'sanctum')
            ->getJson("/api/complaints/{$complaintA->id}")
            ->assertNotFound();
    }
}
