<?php

namespace Tests\Feature;

use App\Domain\Patient\Models\Patient;
use App\Domain\Qualite\Models\Complaint;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplaintTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Patient $patientA;

    private User $secretaireA;

    private User $directionA;

    private User $gestionnaire1;

    private User $gestionnaire2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structureA = Structure::factory()->create();
        $this->patientA = Patient::factory()->for($this->structureA)->create();

        $this->secretaireA = User::factory()->for($this->structureA)->create();
        $this->secretaireA->assignRole('secretaire');

        $this->directionA = User::factory()->for($this->structureA)->create();
        $this->directionA->assignRole('direction');

        $this->gestionnaire1 = User::factory()->for($this->structureA)->create();
        $this->gestionnaire1->assignRole('secretaire');

        $this->gestionnaire2 = User::factory()->for($this->structureA)->create();
        $this->gestionnaire2->assignRole('secretaire');
    }

    // --- Workflow ordonné complet : ouverte -> en_cours -> resolue -> close

    public function test_the_full_ordered_workflow_can_be_completed_step_by_step(): void
    {
        $complaintId = $this->actingAs($this->secretaireA)->postJson('/api/complaints', [
            'patient_id' => $this->patientA->id,
            'motif' => 'attente',
            'description' => 'Temps d\'attente trop long en salle.',
            'service_concerne' => 'consultation',
        ])->assertCreated()->json('data.id');

        $this->assertSame('ouverte', Complaint::find($complaintId)->statut);

        $this->actingAs($this->directionA)->postJson("/api/complaints/{$complaintId}/assign", [
            'gestionnaire_id' => $this->gestionnaire1->id,
        ])->assertOk()->assertJsonPath('data.statut', 'en_cours');

        $this->actingAs($this->gestionnaire1)->postJson("/api/complaints/{$complaintId}/respond", [
            'message' => 'Nous étudions votre réclamation.',
        ])->assertCreated();

        $resolved = $this->actingAs($this->gestionnaire1)
            ->postJson("/api/complaints/{$complaintId}/resolve")
            ->assertOk();
        $this->assertSame('resolue', $resolved->json('data.statut'));
        $this->assertNotNull(Complaint::find($complaintId)->resolved_at);

        $closed = $this->actingAs($this->gestionnaire1)
            ->postJson("/api/complaints/{$complaintId}/close")
            ->assertOk();
        $this->assertSame('close', $closed->json('data.statut'));
        $this->assertNotNull(Complaint::find($complaintId)->closed_at);

        $show = $this->actingAs($this->gestionnaire1)
            ->getJson("/api/complaints/{$complaintId}")
            ->assertOk();
        $this->assertCount(1, $show->json('data.responses'));
    }

    // --- Transitions hors-ordre rejetées en 422 ----------------------------

    public function test_a_complaint_cannot_skip_from_open_to_resolved(): void
    {
        $complaint = Complaint::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'statut' => 'ouverte',
        ]);

        $this->actingAs($this->directionA)
            ->postJson("/api/complaints/{$complaint->id}/resolve")
            ->assertStatus(422);

        $this->assertSame('ouverte', $complaint->fresh()->statut);
    }

    public function test_a_complaint_in_progress_cannot_be_closed_without_being_resolved_first(): void
    {
        $complaint = Complaint::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'gestionnaire_id' => $this->gestionnaire1->id,
            'statut' => 'en_cours',
        ]);

        $this->actingAs($this->gestionnaire1)
            ->postJson("/api/complaints/{$complaint->id}/close")
            ->assertStatus(422);

        $this->assertSame('en_cours', $complaint->fresh()->statut);
    }

    public function test_an_already_assigned_complaint_cannot_be_assigned_again(): void
    {
        $complaint = Complaint::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'gestionnaire_id' => $this->gestionnaire1->id,
            'statut' => 'en_cours',
        ]);

        $this->actingAs($this->directionA)
            ->postJson("/api/complaints/{$complaint->id}/assign", ['gestionnaire_id' => $this->gestionnaire2->id])
            ->assertStatus(422);
    }

    // --- Portée "own scope" du gestionnaire ---------------------------------

    public function test_a_manager_cannot_act_on_a_complaint_assigned_to_someone_else(): void
    {
        $complaint = Complaint::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'gestionnaire_id' => $this->gestionnaire1->id,
            'statut' => 'en_cours',
        ]);

        $this->actingAs($this->gestionnaire2)
            ->getJson("/api/complaints/{$complaint->id}")
            ->assertForbidden();

        $this->actingAs($this->gestionnaire2)
            ->postJson("/api/complaints/{$complaint->id}/respond", ['message' => 'Je regarde ça.'])
            ->assertForbidden();

        $this->actingAs($this->gestionnaire2)
            ->postJson("/api/complaints/{$complaint->id}/resolve")
            ->assertForbidden();
    }

    public function test_index_only_returns_complaints_assigned_to_the_current_manager_without_manage_all(): void
    {
        Complaint::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id, 'gestionnaire_id' => $this->gestionnaire1->id, 'statut' => 'en_cours',
        ]);
        Complaint::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id, 'gestionnaire_id' => $this->gestionnaire2->id, 'statut' => 'en_cours',
        ]);

        $response = $this->actingAs($this->gestionnaire1)
            ->getJson('/api/complaints')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($this->gestionnaire1->id, $response->json('data.0.gestionnaire_id'));
    }

    public function test_a_manage_all_holder_can_act_on_any_complaint_regardless_of_assignment(): void
    {
        $complaint = Complaint::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'gestionnaire_id' => $this->gestionnaire1->id,
            'statut' => 'en_cours',
        ]);

        // direction possède reclamations.manage_all : pas assignée, doit tout de même pouvoir agir.
        $this->actingAs($this->directionA)
            ->postJson("/api/complaints/{$complaint->id}/resolve")
            ->assertOk()
            ->assertJsonPath('data.statut', 'resolue');

        $response = $this->actingAs($this->directionA)
            ->getJson('/api/complaints')
            ->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    // --- Identité du gestionnaire / de l'auteur / du résolveur / du clôtureur -

    /**
     * Régression audit élargi (même bug report que la checklist bloc
     * opératoire) : deux natures de lacune coexistaient ici. gestionnaire_id
     * et auteur_id (réponse) étaient déjà capturés mais jamais exposés comme
     * nom résolu (lacune d'exposition, comme la checklist). resolved_by et
     * closed_by, eux, n'existaient même pas en base avant la migration
     * 2026_09_03_000002 (lacune de capture, comme le compte rendu
     * d'imagerie) — resolved_at/closed_at ne traçaient qu'une date, jamais
     * qui avait résolu ou clôturé.
     */
    public function test_the_manager_author_resolver_and_closer_identities_are_returned_by_the_api(): void
    {
        $complaintId = $this->actingAs($this->secretaireA)->postJson('/api/complaints', [
            'patient_id' => $this->patientA->id,
            'motif' => 'attente',
            'description' => 'Temps d\'attente trop long en salle.',
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->directionA)->postJson("/api/complaints/{$complaintId}/assign", [
            'gestionnaire_id' => $this->gestionnaire1->id,
        ])->assertOk();

        $this->actingAs($this->gestionnaire1)->postJson("/api/complaints/{$complaintId}/respond", [
            'message' => 'Nous étudions votre réclamation.',
        ])->assertCreated();

        $this->actingAs($this->gestionnaire1)->postJson("/api/complaints/{$complaintId}/resolve")->assertOk();
        $this->actingAs($this->gestionnaire1)->postJson("/api/complaints/{$complaintId}/close")->assertOk();

        $data = $this->actingAs($this->gestionnaire1)
            ->getJson("/api/complaints/{$complaintId}")
            ->assertOk()
            ->json('data');

        $expectedName = trim("{$this->gestionnaire1->first_name} {$this->gestionnaire1->last_name}");

        $this->assertSame($expectedName, $data['gestionnaire_label']);
        $this->assertSame('secretaire', $data['gestionnaire_role']);

        $this->assertSame($this->gestionnaire1->id, $data['resolved_by']);
        $this->assertSame($expectedName, $data['resolved_by_label']);

        $this->assertSame($this->gestionnaire1->id, $data['closed_by']);
        $this->assertSame($expectedName, $data['closed_by_label']);

        $this->assertSame($expectedName, $data['responses'][0]['auteur_label']);
        $this->assertSame('secretaire', $data['responses'][0]['auteur_role']);
    }

    // --- Origine (portail patient) ------------------------------------------

    public function test_a_complaint_created_by_staff_has_the_staff_origin(): void
    {
        $complaintId = $this->actingAs($this->secretaireA)->postJson('/api/complaints', [
            'patient_id' => $this->patientA->id,
            'motif' => 'attente',
            'description' => 'Temps d\'attente trop long en salle.',
        ])->assertCreated()->json('data.id');

        $this->assertSame('staff', Complaint::find($complaintId)->origin);
    }
}
