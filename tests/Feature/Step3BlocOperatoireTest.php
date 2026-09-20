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

    // --- Régression : résolution du site sur le formulaire de planification --

    /**
     * Régression bug report : un chirurgien rattaché à un seul site personnel
     * doit voir ce site sans jamais recevoir de 403 sur GET /sites (repli
     * manuel de useSiteSelection côté frontend quand l'auto-résolution
     * échoue). Couvre le second correctif — sites.view accordé au rôle
     * chirurgien dans RolePermissionSeeder — indépendamment du premier
     * correctif (eager-load de `sites` sur la connexion), testé dans
     * AuthTest::test_login_response_includes_the_users_sites.
     */
    public function test_chirurgien_with_a_personal_site_can_list_structure_sites(): void
    {
        $this->chirurgienA->sites()->attach($this->siteA->id);

        $this->actingAs($this->chirurgienA)
            ->getJson('/api/sites')
            ->assertOk()
            ->assertJsonFragment(['id' => $this->siteA->id]);
    }

    // --- Sélection par nom (patient / chirurgien / anesthésiste) -------------

    /**
     * Régression bug report : le formulaire de planification sélectionnait
     * patient/chirurgien/anesthésiste par ID numérique brut. /api/practitioners
     * doit filtrer par rôle pour que le frontend puisse peupler des listes
     * déroulantes par nom (chirurgien/anesthésiste), sans exposer tous les
     * praticiens de la structure indistinctement.
     */
    public function test_practitioners_endpoint_filters_by_role(): void
    {
        $surgeons = $this->actingAs($this->secretaireA)
            ->getJson('/api/practitioners?role=chirurgien')
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $surgeons);
        $this->assertSame($this->chirurgienA->id, $surgeons[0]['id']);

        $anesthesiologists = $this->actingAs($this->secretaireA)
            ->getJson('/api/practitioners?role=anesthesiste')
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $anesthesiologists);
        $this->assertSame($this->anesthesisteA->id, $anesthesiologists[0]['id']);
    }

    /**
     * Bout en bout : planifier une intervention en résolvant patient,
     * chirurgien et anesthésiste par nom — via la recherche patient et le
     * filtre de rôle de /api/practitioners — plutôt qu'en connaissant un ID à
     * l'avance, exactement le parcours que suit désormais
     * PlanSurgicalProcedureDialog côté frontend.
     */
    public function test_can_plan_a_procedure_with_participants_resolved_by_name(): void
    {
        $foundPatient = $this->actingAs($this->secretaireA)
            ->getJson('/api/patients?search='.urlencode($this->patientA->last_name))
            ->assertOk()
            ->json('data.0');
        $this->assertSame($this->patientA->id, $foundPatient['id']);

        $surgeon = $this->actingAs($this->secretaireA)
            ->getJson('/api/practitioners?role=chirurgien')
            ->assertOk()
            ->json('data.0');
        $anesthesiologist = $this->actingAs($this->secretaireA)
            ->getJson('/api/practitioners?role=anesthesiste')
            ->assertOk()
            ->json('data.0');

        $this->actingAs($this->chirurgienA)->postJson('/api/surgical-procedures', [
            'site_id' => $this->siteA->id,
            'patient_id' => $foundPatient['id'],
            'surgeon_id' => $surgeon['id'],
            'anesthesiologist_id' => $anesthesiologist['id'],
            'operating_room' => 'Bloc 1',
            'procedure_type' => 'Appendicectomie',
            'scheduled_at' => now()->addDay()->toDateTimeString(),
        ])->assertCreated()->assertJsonPath('data.status', 'planifiee');
    }

    // --- Identité du validateur de la checklist -------------------------------

    /**
     * Régression bug report QA : chaque étape validée de la checklist
     * chirurgicale tracait déjà l'utilisateur (validated_by, capture
     * inchangée) mais ne l'exposait jamais — ni le contrôleur (eager-load),
     * ni la resource. Le nom (et le rôle) du validateur doivent désormais
     * être renvoyés par GET /surgical-procedures/{id}.
     */
    public function test_the_checklist_validator_identity_is_returned_by_the_api(): void
    {
        $procedure = $this->schedule();
        $this->actingAs($this->chirurgienA)->postJson("/api/surgical-procedures/{$procedure['id']}/start")->assertOk();

        $this->validateStep($procedure, 'avant_anesthesie')->assertCreated();

        $response = $this->actingAs($this->chirurgienA)
            ->getJson("/api/surgical-procedures/{$procedure['id']}")
            ->assertOk();

        $checklist = collect($response->json('data.checklists'))
            ->firstWhere('step', 'avant_anesthesie');

        $this->assertSame($this->anesthesisteA->id, $checklist['validated_by']);
        $this->assertSame(
            trim("{$this->anesthesisteA->first_name} {$this->anesthesisteA->last_name}"),
            $checklist['validator_label'],
        );
        $this->assertSame('anesthesiste', $checklist['validator_role']);
    }
}
