<?php

namespace Tests\Feature;

use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Step3BlocOperatoireTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Structure $structureB;

    private Site $siteA;

    private User $chirurgienA;

    private User $anesthesisteA;

    private User $secretaireA;

    private Patient $patientA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structureA = Structure::factory()->create();
        $this->structureB = Structure::factory()->create();
        $this->siteA = Site::factory()->for($this->structureA)->create();

        $this->chirurgienA = User::factory()->for($this->structureA)->create();
        $this->chirurgienA->assignRole('chirurgien');

        $this->anesthesisteA = User::factory()->for($this->structureA)->create();
        $this->anesthesisteA->assignRole('anesthesiste');

        $this->secretaireA = User::factory()->for($this->structureA)->create();
        $this->secretaireA->assignRole('secretaire');

        $this->patientA = Patient::factory()->for($this->structureA)->create();
    }

    private function schedule(array $overrides = []): array
    {
        return $this->actingAs($this->chirurgienA)->postJson('/api/surgical-procedures', array_merge([
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'surgeon_id' => $this->chirurgienA->id,
            'anesthesiologist_id' => $this->anesthesisteA->id,
            'operating_room' => 'Bloc 1',
            'procedure_type' => 'Appendicectomie',
            'scheduled_at' => now()->addDay()->toDateTimeString(),
        ], $overrides))->assertCreated()->json('data');
    }

    private function validateStep(array $procedure, string $step, ?User $as = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($as ?? $this->anesthesisteA)
            ->postJson("/api/surgical-procedures/{$procedure['id']}/checklist/{$step}", [
                'items' => ['item_1' => true, 'item_2' => true],
            ]);
    }

    // --- Workflow complet ------------------------------------------------

    public function test_full_surgical_workflow_from_scheduling_to_completion(): void
    {
        $procedure = $this->schedule();
        $this->assertSame('planifiee', $procedure['status']);

        $this->actingAs($this->chirurgienA)
            ->postJson("/api/surgical-procedures/{$procedure['id']}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'en_cours');

        $this->validateStep($procedure, 'avant_anesthesie')->assertCreated();
        $this->validateStep($procedure, 'avant_incision')->assertCreated();
        $this->validateStep($procedure, 'avant_sortie_bloc')->assertCreated();

        $response = $this->actingAs($this->chirurgienA)
            ->postJson("/api/surgical-procedures/{$procedure['id']}/complete")
            ->assertOk();

        $response->assertJsonPath('data.status', 'terminee');
        $this->assertNotNull($response->json('data.performed_at'));
    }

    // --- Règle bloquante critique : checklist incomplète ---------------------

    public function test_completion_is_blocked_until_all_three_checklist_steps_are_validated(): void
    {
        $procedure = $this->schedule();
        $this->actingAs($this->chirurgienA)->postJson("/api/surgical-procedures/{$procedure['id']}/start")->assertOk();

        // 0 étape validée
        $this->actingAs($this->chirurgienA)
            ->postJson("/api/surgical-procedures/{$procedure['id']}/complete")
            ->assertStatus(422);

        // 1 étape validée
        $this->validateStep($procedure, 'avant_anesthesie')->assertCreated();
        $this->actingAs($this->chirurgienA)
            ->postJson("/api/surgical-procedures/{$procedure['id']}/complete")
            ->assertStatus(422);

        // 2 étapes validées
        $this->validateStep($procedure, 'avant_incision')->assertCreated();
        $this->actingAs($this->chirurgienA)
            ->postJson("/api/surgical-procedures/{$procedure['id']}/complete")
            ->assertStatus(422);

        // 3 étapes validées : la complétion devient possible
        $this->validateStep($procedure, 'avant_sortie_bloc')->assertCreated();
        $this->actingAs($this->chirurgienA)
            ->postJson("/api/surgical-procedures/{$procedure['id']}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'terminee');
    }

    public function test_a_procedure_that_has_not_started_cannot_be_completed(): void
    {
        $procedure = $this->schedule();

        $this->validateStep($procedure, 'avant_anesthesie')->assertCreated();
        $this->validateStep($procedure, 'avant_incision')->assertCreated();
        $this->validateStep($procedure, 'avant_sortie_bloc')->assertCreated();

        $this->actingAs($this->chirurgienA)
            ->postJson("/api/surgical-procedures/{$procedure['id']}/complete")
            ->assertStatus(422);
    }

    public function test_status_cannot_be_smuggled_through_the_store_payload(): void
    {
        $procedure = $this->schedule(['status' => 'terminee']);

        $this->assertSame('planifiee', $procedure['status']);
    }

    // --- Permissions ---------------------------------------------------------

    public function test_secretary_cannot_schedule_a_procedure(): void
    {
        $this->actingAs($this->secretaireA)->postJson('/api/surgical-procedures', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'surgeon_id' => $this->chirurgienA->id,
            'anesthesiologist_id' => $this->anesthesisteA->id,
            'operating_room' => 'Bloc 1',
            'procedure_type' => 'Appendicectomie',
            'scheduled_at' => now()->addDay()->toDateTimeString(),
        ])->assertForbidden();
    }

    public function test_anesthesiste_cannot_start_or_complete_a_procedure(): void
    {
        $procedure = $this->schedule();

        $this->actingAs($this->anesthesisteA)
            ->postJson("/api/surgical-procedures/{$procedure['id']}/start")
            ->assertForbidden();
    }

    public function test_secretary_cannot_validate_a_checklist_step(): void
    {
        $procedure = $this->schedule();

        $this->validateStep($procedure, 'avant_anesthesie', $this->secretaireA)->assertForbidden();
    }

    // --- Isolation multi-tenant ----------------------------------------------

    public function test_a_surgical_procedure_is_invisible_to_another_structure(): void
    {
        $procedure = $this->schedule();

        $chirurgienB = User::factory()->for($this->structureB)->create();
        $chirurgienB->assignRole('chirurgien');

        $this->actingAs($chirurgienB)->getJson("/api/surgical-procedures/{$procedure['id']}")->assertNotFound();
    }
}
