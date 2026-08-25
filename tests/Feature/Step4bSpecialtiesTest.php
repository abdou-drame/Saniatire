<?php

namespace Tests\Feature;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Step4bSpecialtiesTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Structure $structureB;

    private Site $siteA;

    private User $ophtalmologueA;

    private User $cardiologueA;

    private User $kinesitherapeuteA;

    private User $oncologueA;

    private User $specialistePmaA;

    private User $psychiatreA;

    private User $pediatreA;

    private User $medecinTravailA;

    private User $infirmierDomicileA;

    private User $dentisteA;

    private User $directionA;

    private User $secretaireA;

    private User $administrateurA;

    private Patient $patientA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structureA = Structure::factory()->create();
        $this->structureB = Structure::factory()->create();
        $this->siteA = Site::factory()->for($this->structureA)->create();

        $this->ophtalmologueA = User::factory()->for($this->structureA)->create();
        $this->ophtalmologueA->assignRole('ophtalmologue');

        $this->cardiologueA = User::factory()->for($this->structureA)->create();
        $this->cardiologueA->assignRole('cardiologue');

        $this->kinesitherapeuteA = User::factory()->for($this->structureA)->create();
        $this->kinesitherapeuteA->assignRole('kinesitherapeute');

        $this->oncologueA = User::factory()->for($this->structureA)->create();
        $this->oncologueA->assignRole('oncologue');

        $this->specialistePmaA = User::factory()->for($this->structureA)->create();
        $this->specialistePmaA->assignRole('specialiste_pma');

        $this->psychiatreA = User::factory()->for($this->structureA)->create();
        $this->psychiatreA->assignRole('psychiatre');

        $this->pediatreA = User::factory()->for($this->structureA)->create();
        $this->pediatreA->assignRole('pediatre');

        $this->medecinTravailA = User::factory()->for($this->structureA)->create();
        $this->medecinTravailA->assignRole('medecin_travail');

        $this->infirmierDomicileA = User::factory()->for($this->structureA)->create();
        $this->infirmierDomicileA->assignRole('infirmier_domicile');

        // Deliberately unrelated clinical role, used to prove pma/sante_mentale
        // are inaccessible even to another medical specialist (not just to
        // non-clinical staff) — the user's explicit "ex. dentiste" example.
        $this->dentisteA = User::factory()->for($this->structureA)->create();
        $this->dentisteA->assignRole('dentiste');

        $this->directionA = User::factory()->for($this->structureA)->create();
        $this->directionA->assignRole('direction');

        // Non-clinical administrative role, used to prove pma/sante_mentale
        // are inaccessible to administrative staff (§3 of the verification
        // prompt: secrétaire must not see, or very limited).
        $this->secretaireA = User::factory()->for($this->structureA)->create();
        $this->secretaireA->assignRole('secretaire');

        // Technical superuser role: intentionally granted every permission
        // row (see RolePermissionSeeder — enable_wildcard_permission is
        // false, so 'administrateur' => ['*'] is resolved by explicitly
        // syncing all existing Permission rows, not fuzzy wildcard
        // matching). Used to document, not hide, this deliberate exception.
        $this->administrateurA = User::factory()->for($this->structureA)->create();
        $this->administrateurA->assignRole('administrateur');

        $this->patientA = Patient::factory()->for($this->structureA)->create();
    }

    // --- Helpers -----------------------------------------------------------

    private function createOphtalmoRecord(?User $as = null, array $overrides = []): array
    {
        return $this->actingAs($as ?? $this->ophtalmologueA)->postJson('/api/ophtalmo-records', array_merge([
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'examined_at' => '2026-08-01',
        ], $overrides))->assertCreated()->json('data');
    }

    private function createCardioRecord(?User $as = null, array $overrides = []): array
    {
        return $this->actingAs($as ?? $this->cardiologueA)->postJson('/api/cardio-records', array_merge([
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'examined_at' => '2026-08-01',
        ], $overrides))->assertCreated()->json('data');
    }

    private function createKineProgram(?User $as = null, array $overrides = []): array
    {
        return $this->actingAs($as ?? $this->kinesitherapeuteA)->postJson('/api/kine-programs', array_merge([
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'affected_area' => 'epaule droite',
            'started_at' => '2026-08-01',
        ], $overrides))->assertCreated()->json('data');
    }

    private function createOncoRecord(?User $as = null, array $overrides = []): array
    {
        return $this->actingAs($as ?? $this->oncologueA)->postJson('/api/onco-records', array_merge([
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'cancer_type' => 'sein',
            'stage_t' => 'T2',
            'stage_n' => 'N1',
            'stage_m' => 'M0',
        ], $overrides))->assertCreated()->json('data');
    }

    private function createPmaRecord(?User $as = null, array $overrides = []): array
    {
        return $this->actingAs($as ?? $this->specialistePmaA)->postJson('/api/pma-records', array_merge([
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
        ], $overrides))->assertCreated()->json('data');
    }

    private function createMentalHealthRecord(?User $as = null, array $overrides = []): array
    {
        return $this->actingAs($as ?? $this->psychiatreA)->postJson('/api/mental-health-records', array_merge([
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'consultation_reason' => 'Episode anxieux',
        ], $overrides))->assertCreated()->json('data');
    }

    private function createPediatricRecord(?User $as = null, array $overrides = []): array
    {
        return $this->actingAs($as ?? $this->pediatreA)->postJson('/api/pediatric-records', array_merge([
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
        ], $overrides))->assertCreated()->json('data');
    }

    private function createOccupationalHealthRecord(?User $as = null, array $overrides = []): array
    {
        return $this->actingAs($as ?? $this->medecinTravailA)->postJson('/api/occupational-health-records', array_merge([
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'visit_type' => 'periodique',
            'fitness_status' => 'apte',
            'visit_date' => '2026-08-01',
        ], $overrides))->assertCreated()->json('data');
    }

    private function createHomeCareRecord(?User $as = null, array $overrides = []): array
    {
        return $this->actingAs($as ?? $this->infirmierDomicileA)->postJson('/api/home-care-records', array_merge([
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'intervention_address' => '12 rue des Fleurs',
            'care_type' => 'pansement',
        ], $overrides))->assertCreated()->json('data');
    }

    // --- Ophtalmologie -------------------------------------------------------

    public function test_ophtalmologue_can_create_a_record_and_link_it_to_a_consultation(): void
    {
        $consultation = Consultation::factory()->for($this->structureA)->for($this->patientA)->create();

        $record = $this->createOphtalmoRecord(overrides: [
            'consultation_id' => $consultation->id,
            'visual_acuity_od_corrected' => '10/10',
            'intraocular_pressure_od' => 16,
            'refraction_od_sphere' => -1.25,
        ]);

        $this->assertSame('10/10', $record['visual_acuity_od_corrected']);
        $this->assertSame('ophtalmo', $consultation->fresh()->specialty_type);
    }

    public function test_ophtalmo_intraocular_pressure_outside_valid_range_is_rejected(): void
    {
        $this->actingAs($this->ophtalmologueA)->postJson('/api/ophtalmo-records', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'examined_at' => '2026-08-01',
            'intraocular_pressure_od' => 999,
        ])->assertStatus(422)->assertJsonValidationErrors('intraocular_pressure_od');
    }

    public function test_an_ophtalmo_record_is_invisible_to_another_structure(): void
    {
        $record = $this->createOphtalmoRecord();

        $ophtalmologueB = User::factory()->for($this->structureB)->create();
        $ophtalmologueB->assignRole('ophtalmologue');

        $this->actingAs($ophtalmologueB)->getJson("/api/ophtalmo-records/{$record['id']}")->assertNotFound();
    }

    // --- Cardiologie -----------------------------------------------------------

    public function test_cardiologue_can_record_readings_and_an_ecg_result(): void
    {
        $record = $this->createCardioRecord();

        $this->actingAs($this->cardiologueA)
            ->postJson("/api/cardio-records/{$record['id']}/readings", [
                'measured_at' => '2026-08-01 10:00:00',
                'blood_pressure_systolic' => 130,
                'blood_pressure_diastolic' => 85,
                'heart_rate' => 72,
                'rhythm' => 'regulier',
            ])->assertCreated();

        $this->actingAs($this->cardiologueA)
            ->postJson("/api/cardio-records/{$record['id']}/ecg-results", [
                'performed_at' => '2026-08-01 10:15:00',
                'rhythm' => 'sinusal',
                'heart_rate' => 72,
            ])->assertCreated();

        $show = $this->actingAs($this->cardiologueA)
            ->getJson("/api/cardio-records/{$record['id']}")
            ->assertOk()->json('data');

        $this->assertCount(1, $show['readings']);
        $this->assertCount(1, $show['ecg_results']);
    }

    public function test_cardio_reading_requires_blood_pressure(): void
    {
        $record = $this->createCardioRecord();

        $this->actingAs($this->cardiologueA)
            ->postJson("/api/cardio-records/{$record['id']}/readings", [
                'measured_at' => '2026-08-01 10:00:00',
                'heart_rate' => 72,
            ])->assertStatus(422)->assertJsonValidationErrors('blood_pressure_systolic');
    }

    public function test_a_cardio_reading_cannot_be_recorded_on_another_structures_record(): void
    {
        $record = $this->createCardioRecord();

        $cardiologueB = User::factory()->for($this->structureB)->create();
        $cardiologueB->assignRole('cardiologue');

        $this->actingAs($cardiologueB)
            ->postJson("/api/cardio-records/{$record['id']}/readings", [
                'measured_at' => '2026-08-01 10:00:00',
                'blood_pressure_systolic' => 130,
                'blood_pressure_diastolic' => 85,
                'heart_rate' => 72,
            ])->assertNotFound();
    }

    public function test_cardio_reading_with_an_incoherent_heart_rate_is_rejected(): void
    {
        $record = $this->createCardioRecord();

        $this->actingAs($this->cardiologueA)
            ->postJson("/api/cardio-records/{$record['id']}/readings", [
                'measured_at' => '2026-08-01 10:00:00',
                'blood_pressure_systolic' => 120,
                'blood_pressure_diastolic' => 80,
                'heart_rate' => 0,
            ])->assertStatus(422)->assertJsonValidationErrors('heart_rate');

        $this->actingAs($this->cardiologueA)
            ->postJson("/api/cardio-records/{$record['id']}/readings", [
                'measured_at' => '2026-08-01 10:00:00',
                'blood_pressure_systolic' => 120,
                'blood_pressure_diastolic' => 80,
                'heart_rate' => 400,
            ])->assertStatus(422)->assertJsonValidationErrors('heart_rate');
    }

    // --- Kinésithérapie -----------------------------------------------------

    public function test_kinesitherapeute_can_record_a_session_under_a_program(): void
    {
        $program = $this->createKineProgram();

        $this->actingAs($this->kinesitherapeuteA)
            ->postJson("/api/kine-programs/{$program['id']}/sessions", [
                'session_date' => '2026-08-05',
                'exercises_performed' => 'Mobilisation active',
                'pain_scale' => 3,
            ])->assertCreated()->assertJsonPath('data.pain_scale', 3);
    }

    public function test_kine_session_pain_scale_outside_valid_range_is_rejected(): void
    {
        $program = $this->createKineProgram();

        $this->actingAs($this->kinesitherapeuteA)
            ->postJson("/api/kine-programs/{$program['id']}/sessions", [
                'session_date' => '2026-08-05',
                'pain_scale' => 42,
            ])->assertStatus(422)->assertJsonValidationErrors('pain_scale');
    }

    public function test_a_kine_program_is_invisible_to_another_structure(): void
    {
        $program = $this->createKineProgram();

        $kineB = User::factory()->for($this->structureB)->create();
        $kineB->assignRole('kinesitherapeute');

        $this->actingAs($kineB)->getJson("/api/kine-programs/{$program['id']}")->assertNotFound();
    }

    // --- Oncologie -----------------------------------------------------------

    public function test_oncologue_can_record_a_chemo_cycle_and_a_response_evaluation(): void
    {
        $record = $this->createOncoRecord();

        $this->actingAs($this->oncologueA)
            ->postJson("/api/onco-records/{$record['id']}/chemo-cycles", [
                'cycle_number' => 1,
                'cycle_date' => '2026-08-05',
                'medications' => 'FEC100',
            ])->assertCreated();

        $this->actingAs($this->oncologueA)
            ->postJson("/api/onco-records/{$record['id']}/response-evaluations", [
                'evaluated_at' => '2026-09-01',
                'response' => 'reponse_partielle',
            ])->assertCreated()->assertJsonPath('data.response', 'reponse_partielle');
    }

    public function test_inconsistent_tnm_stage_is_rejected(): void
    {
        $this->actingAs($this->oncologueA)->postJson('/api/onco-records', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'cancer_type' => 'sein',
            'stage_t' => 'T9',
        ])->assertStatus(422)->assertJsonValidationErrors('stage_t');
    }

    public function test_onco_chemo_cycle_history_is_preserved_in_chronological_order(): void
    {
        $record = $this->createOncoRecord();

        $this->actingAs($this->oncologueA)->postJson("/api/onco-records/{$record['id']}/chemo-cycles", [
            'cycle_number' => 1,
            'cycle_date' => '2026-08-05',
            'medications' => 'FEC100',
        ])->assertCreated();

        $this->actingAs($this->oncologueA)->postJson("/api/onco-records/{$record['id']}/chemo-cycles", [
            'cycle_number' => 2,
            'cycle_date' => '2026-08-26',
            'medications' => 'FEC100',
        ])->assertCreated();

        $this->actingAs($this->oncologueA)->postJson("/api/onco-records/{$record['id']}/chemo-cycles", [
            'cycle_number' => 3,
            'cycle_date' => '2026-09-16',
            'medications' => 'FEC100',
        ])->assertCreated();

        $show = $this->actingAs($this->oncologueA)
            ->getJson("/api/onco-records/{$record['id']}")
            ->assertOk()->json('data');

        $this->assertCount(3, $show['chemo_cycles']);
        $this->assertSame([1, 2, 3], array_column($show['chemo_cycles'], 'cycle_number'));
    }

    public function test_an_onco_record_is_invisible_to_another_structure(): void
    {
        $record = $this->createOncoRecord();

        $oncologueB = User::factory()->for($this->structureB)->create();
        $oncologueB->assignRole('oncologue');

        $this->actingAs($oncologueB)->getJson("/api/onco-records/{$record['id']}")->assertNotFound();
    }

    // --- PMA / Fertilité -------------------------------------------------------

    public function test_specialiste_pma_can_record_a_stimulation_protocol_and_a_cycle_monitoring(): void
    {
        $record = $this->createPmaRecord();

        $this->actingAs($this->specialistePmaA)
            ->postJson("/api/pma-records/{$record['id']}/stimulation-protocols", [
                'protocol_type' => 'antagoniste',
                'started_at' => '2026-08-01',
            ])->assertCreated();

        $this->actingAs($this->specialistePmaA)
            ->postJson("/api/pma-records/{$record['id']}/cycle-monitorings", [
                'monitoring_date' => '2026-08-10',
                'hormone_level' => 250.5,
                'puncture_date' => '2026-08-14',
            ])->assertCreated();

        $show = $this->actingAs($this->specialistePmaA)
            ->getJson("/api/pma-records/{$record['id']}")
            ->assertOk()->json('data');

        $this->assertCount(1, $show['stimulation_protocols']);
        $this->assertSame('antagoniste', $show['stimulation_protocols'][0]['protocol_type']);
        $this->assertCount(1, $show['cycle_monitorings']);
        $this->assertEquals(250.5, $show['cycle_monitorings'][0]['hormone_level']);
    }

    public function test_pma_attempt_result_outside_allowed_values_is_rejected(): void
    {
        $record = $this->createPmaRecord();

        $this->actingAs($this->specialistePmaA)
            ->putJson("/api/pma-records/{$record['id']}", ['attempt_result' => 'inconnu'])
            ->assertStatus(422)->assertJsonValidationErrors('attempt_result');
    }

    public function test_dentiste_cannot_view_or_create_pma_records(): void
    {
        $record = $this->createPmaRecord();

        $this->actingAs($this->dentisteA)->getJson("/api/pma-records/{$record['id']}")->assertForbidden();
        $this->actingAs($this->dentisteA)->getJson('/api/pma-records')->assertForbidden();
        $this->actingAs($this->dentisteA)->postJson('/api/pma-records', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
        ])->assertForbidden();
    }

    public function test_direction_role_cannot_view_pma_records(): void
    {
        $record = $this->createPmaRecord();

        $this->actingAs($this->directionA)->getJson("/api/pma-records/{$record['id']}")->assertForbidden();
        $this->actingAs($this->directionA)->getJson('/api/pma-stats')->assertForbidden();
    }

    public function test_secretaire_cannot_view_or_create_pma_records(): void
    {
        $record = $this->createPmaRecord();

        $this->actingAs($this->secretaireA)->getJson("/api/pma-records/{$record['id']}")->assertForbidden();
        $this->actingAs($this->secretaireA)->getJson('/api/pma-records')->assertForbidden();
        $this->actingAs($this->secretaireA)->postJson('/api/pma-records', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
        ])->assertForbidden();
    }

    /**
     * administrateur is the technical superuser role and, unlike every
     * other non-specialist role, DOES have pma.view (RolePermissionSeeder
     * syncs it every existing Permission row — see setUp() comment). This
     * test documents that deliberate exception rather than hiding it: the
     * verification prompt (§3) asks to check "what access level was
     * chosen and whether it's consistent" for the sysadmin profile.
     */
    public function test_administrateur_can_view_pma_records_as_a_documented_exception(): void
    {
        $record = $this->createPmaRecord();

        $this->actingAs($this->administrateurA)->getJson("/api/pma-records/{$record['id']}")->assertOk();
    }

    public function test_pma_access_authorized_and_denied_attempts_are_traced_in_the_audit_log(): void
    {
        $record = $this->createPmaRecord();

        $this->actingAs($this->specialistePmaA)->getJson("/api/pma-records/{$record['id']}")->assertOk();
        $this->actingAs($this->dentisteA)->getJson("/api/pma-records/{$record['id']}")->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'acces_sensible',
            'causer_id' => $this->specialistePmaA->id,
            'description' => 'dossier PMA consulté',
        ]);

        $denied = \Spatie\Activitylog\Models\Activity::query()
            ->where('log_name', 'acces_sensible')
            ->where('causer_id', $this->dentisteA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($denied, 'Access denial to a PMA record was not traced in the audit log.');
        $this->assertSame('refuse', $denied->getExtraProperty('resultat'));
        $this->assertContains('pma.view', $denied->getExtraProperty('permissions_requises'));
    }

    /**
     * Documents a real architectural limit rather than hiding it (§3 asks
     * to check the raw-Eloquent path explicitly): confidentiality for
     * pma/sante_mentale is enforced entirely at the HTTP permission-
     * middleware layer (role/permission gate), exactly like every other
     * specialty module in this app. There is no model-level (global scope
     * / policy) restriction by role — only BelongsToTenant's structure
     * scoping. So code running outside the HTTP layer with an unscoped
     * connection (a raw query, a queued job, tinker) is NOT blocked by
     * role. This is consistent app-wide, not a pma/sante_mentale-specific
     * bug — flagged here so the audit report states it explicitly instead
     * of silently asserting "no bypass possible".
     */
    public function test_pma_records_have_no_model_level_role_based_query_guard(): void
    {
        $record = $this->createPmaRecord();

        // Same structure as the record (tenant scope allows it), but a role
        // with no pma.* permission at all — the HTTP layer already proved
        // this 403s in test_dentiste_cannot_view_or_create_pma_records().
        // Acting as this user only sets Auth::user() for TenantScope; a
        // direct Eloquent call bypasses the permission middleware entirely.
        $this->actingAs($this->dentisteA);

        $this->assertNotNull(\App\Domain\Pma\Models\PmaRecord::find($record['id']));
    }

    public function test_a_pma_record_is_invisible_to_another_structure(): void
    {
        $record = $this->createPmaRecord();

        $pmaB = User::factory()->for($this->structureB)->create();
        $pmaB->assignRole('specialiste_pma');

        $this->actingAs($pmaB)->getJson("/api/pma-records/{$record['id']}")->assertNotFound();
    }

    // --- Santé mentale -----------------------------------------------------

    public function test_psychiatre_can_record_a_generic_scale_score(): void
    {
        $record = $this->createMentalHealthRecord();

        $this->actingAs($this->psychiatreA)
            ->postJson("/api/mental-health-records/{$record['id']}/scale-scores", [
                'scale_name' => 'PHQ-9',
                'score' => 14,
                'scored_at' => '2026-08-05',
            ])->assertCreated()->assertJsonPath('data.scale_name', 'PHQ-9');

        // A different, arbitrary scale name is accepted without any code
        // change — proves scoring is generic, not hardcoded per scale.
        $this->actingAs($this->psychiatreA)
            ->postJson("/api/mental-health-records/{$record['id']}/scale-scores", [
                'scale_name' => 'Echelle experimentale locale',
                'score' => 3.5,
                'scored_at' => '2026-08-06',
            ])->assertCreated();
    }

    public function test_mental_health_scale_score_out_of_generic_range_is_rejected(): void
    {
        $record = $this->createMentalHealthRecord();

        $this->actingAs($this->psychiatreA)
            ->postJson("/api/mental-health-records/{$record['id']}/scale-scores", [
                'scale_name' => 'PHQ-9',
                'score' => 100000,
                'scored_at' => '2026-08-05',
            ])->assertStatus(422)->assertJsonValidationErrors('score');
    }

    public function test_dentiste_cannot_view_or_create_mental_health_records(): void
    {
        $record = $this->createMentalHealthRecord();

        $this->actingAs($this->dentisteA)->getJson("/api/mental-health-records/{$record['id']}")->assertForbidden();
        $this->actingAs($this->dentisteA)->getJson('/api/mental-health-records')->assertForbidden();
        $this->actingAs($this->dentisteA)->postJson('/api/mental-health-records', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'consultation_reason' => 'x',
        ])->assertForbidden();
    }

    public function test_direction_role_cannot_view_mental_health_records(): void
    {
        $record = $this->createMentalHealthRecord();

        $this->actingAs($this->directionA)->getJson("/api/mental-health-records/{$record['id']}")->assertForbidden();
        $this->actingAs($this->directionA)->getJson('/api/sante-mentale-stats')->assertForbidden();
    }

    public function test_secretaire_cannot_view_or_create_mental_health_records(): void
    {
        $record = $this->createMentalHealthRecord();

        $this->actingAs($this->secretaireA)->getJson("/api/mental-health-records/{$record['id']}")->assertForbidden();
        $this->actingAs($this->secretaireA)->getJson('/api/mental-health-records')->assertForbidden();
        $this->actingAs($this->secretaireA)->postJson('/api/mental-health-records', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'consultation_reason' => 'x',
        ])->assertForbidden();
    }

    /**
     * Same documented exception as PMA above: administrateur is the
     * technical superuser role and does have sante_mentale.view.
     */
    public function test_administrateur_can_view_mental_health_records_as_a_documented_exception(): void
    {
        $record = $this->createMentalHealthRecord();

        $this->actingAs($this->administrateurA)->getJson("/api/mental-health-records/{$record['id']}")->assertOk();
    }

    public function test_mental_health_access_authorized_and_denied_attempts_are_traced_in_the_audit_log(): void
    {
        $record = $this->createMentalHealthRecord();

        $this->actingAs($this->psychiatreA)->getJson("/api/mental-health-records/{$record['id']}")->assertOk();
        $this->actingAs($this->dentisteA)->getJson("/api/mental-health-records/{$record['id']}")->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'acces_sensible',
            'causer_id' => $this->psychiatreA->id,
            'description' => 'dossier de santé mentale consulté',
        ]);

        $denied = \Spatie\Activitylog\Models\Activity::query()
            ->where('log_name', 'acces_sensible')
            ->where('causer_id', $this->dentisteA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($denied, 'Access denial to a santé mentale record was not traced in the audit log.');
        $this->assertSame('refuse', $denied->getExtraProperty('resultat'));
        $this->assertContains('sante_mentale.view', $denied->getExtraProperty('permissions_requises'));
    }

    public function test_a_mental_health_record_is_invisible_to_another_structure(): void
    {
        $record = $this->createMentalHealthRecord();

        $psyB = User::factory()->for($this->structureB)->create();
        $psyB->assignRole('psychiatre');

        $this->actingAs($psyB)->getJson("/api/mental-health-records/{$record['id']}")->assertNotFound();
    }

    // --- Pédiatrie -----------------------------------------------------------

    public function test_pediatre_can_record_growth_vaccination_and_development_observation(): void
    {
        $record = $this->createPediatricRecord();

        $this->actingAs($this->pediatreA)
            ->postJson("/api/pediatric-records/{$record['id']}/growth-measurements", [
                'measured_at' => '2026-08-01',
                'weight_kg' => 12.4,
                'height_cm' => 85.5,
                'head_circumference_cm' => 47.2,
            ])->assertCreated();

        $this->actingAs($this->pediatreA)
            ->postJson("/api/pediatric-records/{$record['id']}/vaccinations", [
                'vaccine_name' => 'DTP',
                'dose_number' => 2,
                'administered_at' => '2026-08-01',
            ])->assertCreated();

        $this->actingAs($this->pediatreA)
            ->postJson("/api/pediatric-records/{$record['id']}/development-observations", [
                'age_months' => 18,
                'observation' => 'Marche acquise, langage en developpement',
                'observed_at' => '2026-08-01',
            ])->assertCreated();

        $show = $this->actingAs($this->pediatreA)
            ->getJson("/api/pediatric-records/{$record['id']}")
            ->assertOk()->json('data');

        $this->assertCount(1, $show['growth_measurements']);
        $this->assertCount(1, $show['vaccinations']);
        $this->assertCount(1, $show['development_observations']);
    }

    public function test_pediatric_development_observation_age_outside_valid_range_is_rejected(): void
    {
        $record = $this->createPediatricRecord();

        $this->actingAs($this->pediatreA)
            ->postJson("/api/pediatric-records/{$record['id']}/development-observations", [
                'age_months' => 999,
                'observation' => 'x',
                'observed_at' => '2026-08-01',
            ])->assertStatus(422)->assertJsonValidationErrors('age_months');
    }

    public function test_pediatric_growth_measurements_at_different_dates_are_retrievable_in_chronological_order(): void
    {
        $record = $this->createPediatricRecord();

        $this->actingAs($this->pediatreA)->postJson("/api/pediatric-records/{$record['id']}/growth-measurements", [
            'measured_at' => '2026-08-01',
            'weight_kg' => 8.2,
            'height_cm' => 68.0,
        ])->assertCreated();

        $this->actingAs($this->pediatreA)->postJson("/api/pediatric-records/{$record['id']}/growth-measurements", [
            'measured_at' => '2026-02-01',
            'weight_kg' => 6.5,
            'height_cm' => 58.0,
        ])->assertCreated();

        $this->actingAs($this->pediatreA)->postJson("/api/pediatric-records/{$record['id']}/growth-measurements", [
            'measured_at' => '2026-11-01',
            'weight_kg' => 9.8,
            'height_cm' => 72.5,
        ])->assertCreated();

        $show = $this->actingAs($this->pediatreA)
            ->getJson("/api/pediatric-records/{$record['id']}")
            ->assertOk()->json('data');

        $this->assertCount(3, $show['growth_measurements']);
        $this->assertSame(
            ['2026-02-01', '2026-08-01', '2026-11-01'],
            array_map(fn ($m) => substr($m['measured_at'], 0, 10), $show['growth_measurements'])
        );
    }

    public function test_a_pediatric_record_is_invisible_to_another_structure(): void
    {
        $record = $this->createPediatricRecord();

        $pediatreB = User::factory()->for($this->structureB)->create();
        $pediatreB->assignRole('pediatre');

        $this->actingAs($pediatreB)->getJson("/api/pediatric-records/{$record['id']}")->assertNotFound();
    }

    // --- Médecine du travail -------------------------------------------------

    public function test_medecin_travail_can_record_an_apte_avec_reserves_visit_with_restrictions(): void
    {
        $record = $this->createOccupationalHealthRecord(overrides: [
            'fitness_status' => 'apte_avec_reserves',
            'restrictions' => 'Pas de port de charges superieures a 10kg',
            'risk_exposures' => ['bruit', 'poussieres'],
            'next_visit_due_at' => '2027-08-01',
        ]);

        $this->assertSame('apte_avec_reserves', $record['fitness_status']);
        $this->assertSame('Pas de port de charges superieures a 10kg', $record['restrictions']);
    }

    public function test_apte_avec_reserves_without_restrictions_is_rejected(): void
    {
        $this->actingAs($this->medecinTravailA)->postJson('/api/occupational-health-records', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'visit_type' => 'periodique',
            'fitness_status' => 'apte_avec_reserves',
            'visit_date' => '2026-08-01',
        ])->assertStatus(422)->assertJsonValidationErrors('restrictions');
    }

    public function test_an_occupational_health_record_is_invisible_to_another_structure(): void
    {
        $record = $this->createOccupationalHealthRecord();

        $medecinTravailB = User::factory()->for($this->structureB)->create();
        $medecinTravailB->assignRole('medecin_travail');

        $this->actingAs($medecinTravailB)->getJson("/api/occupational-health-records/{$record['id']}")->assertNotFound();
    }

    // --- Soins à domicile ------------------------------------------------------

    public function test_infirmier_domicile_can_record_a_visit_with_an_intervention_address(): void
    {
        $record = $this->createHomeCareRecord(overrides: ['intervention_address' => '5 avenue du Marche']);

        $visit = $this->actingAs($this->infirmierDomicileA)
            ->postJson("/api/home-care-records/{$record['id']}/visits", [
                'care_type' => 'pansement',
                'visit_datetime' => '2026-08-05 09:00:00',
                'report' => 'Plaie propre, evolution favorable.',
            ])->assertCreated()->json('data');

        $this->assertSame($this->infirmierDomicileA->id, $visit['intervenant_id']);

        $show = $this->actingAs($this->infirmierDomicileA)
            ->getJson("/api/home-care-records/{$record['id']}")
            ->assertOk()->json('data');

        $this->assertSame('5 avenue du Marche', $show['intervention_address']);
        $this->assertCount(1, $show['visits']);
    }

    public function test_home_care_visit_requires_a_visit_datetime(): void
    {
        $record = $this->createHomeCareRecord();

        $this->actingAs($this->infirmierDomicileA)
            ->postJson("/api/home-care-records/{$record['id']}/visits", [
                'care_type' => 'pansement',
            ])->assertStatus(422)->assertJsonValidationErrors('visit_datetime');
    }

    public function test_a_home_care_record_is_invisible_to_another_structure(): void
    {
        $record = $this->createHomeCareRecord();

        $infirmierB = User::factory()->for($this->structureB)->create();
        $infirmierB->assignRole('infirmier_domicile');

        $this->actingAs($infirmierB)->getJson("/api/home-care-records/{$record['id']}")->assertNotFound();
    }

    // --- Timeline & statistiques ---------------------------------------------

    public function test_step_4b_specialty_data_appears_in_the_unified_patient_timeline(): void
    {
        $consultation = Consultation::factory()->for($this->structureA)->for($this->patientA)->create([
            'status' => 'terminee',
            'closed_at' => now(),
        ]);

        $this->createOncoRecord(overrides: ['consultation_id' => $consultation->id]);

        $timeline = $this->actingAs($this->oncologueA)
            ->getJson("/api/patients/{$this->patientA->id}/timeline")
            ->assertOk()->json('data');

        $entry = collect($timeline)->firstWhere('type', 'consultation');

        $this->assertNotNull($entry);
        $this->assertSame('oncologie', $entry['data']['specialty_type']);
        $this->assertNotNull($entry['data']['specialty']);
    }

    public function test_multiple_step_4b_specialty_consultations_appear_in_correct_chronological_order_in_the_timeline(): void
    {
        $older = Consultation::factory()->for($this->structureA)->for($this->patientA)->create([
            'status' => 'terminee',
            'closed_at' => '2026-06-01 09:00:00',
        ]);
        $this->createCardioRecord(overrides: ['consultation_id' => $older->id]);

        $newer = Consultation::factory()->for($this->structureA)->for($this->patientA)->create([
            'status' => 'terminee',
            'closed_at' => '2026-08-10 09:00:00',
        ]);
        $this->createOphtalmoRecord(overrides: ['consultation_id' => $newer->id]);

        $timeline = $this->actingAs($this->cardiologueA)
            ->getJson("/api/patients/{$this->patientA->id}/timeline")
            ->assertOk()->json('data');

        $specialtyEntries = collect($timeline)
            ->where('type', 'consultation')
            ->pluck('data.specialty_type')
            ->values();

        // Timeline is sorted newest first: the ophtalmo consultation
        // (2026-08-10) must precede the cardio one (2026-06-01).
        $this->assertSame(['ophtalmo', 'cardiologie'], $specialtyEntries->all());
    }

    /**
     * Critical §3 regression: the unified timeline (PatientController::
     * timeline) is gated only by patients_medical.view, a permission almost
     * every clinical role holds (including dentiste). Before this fix,
     * ConsultationResource embedded the full pma/sante_mentale record
     * regardless of the viewer's own pma.view/sante_mentale.view — a real
     * bypass of the exact confidentiality gate proven elsewhere in this
     * file at the direct-endpoint level. This proves the timeline itself
     * withholds the nested record from an unauthorized viewer while still
     * showing it to an authorized one.
     */
    public function test_pma_and_mental_health_data_is_hidden_from_the_timeline_for_an_unauthorized_viewer(): void
    {
        $pmaConsultation = Consultation::factory()->for($this->structureA)->for($this->patientA)->create([
            'status' => 'terminee', 'closed_at' => '2026-08-01 09:00:00',
        ]);
        $this->createPmaRecord(overrides: ['consultation_id' => $pmaConsultation->id]);

        $smConsultation = Consultation::factory()->for($this->structureA)->for($this->patientA)->create([
            'status' => 'terminee', 'closed_at' => '2026-08-02 09:00:00',
        ]);
        $this->createMentalHealthRecord(overrides: ['consultation_id' => $smConsultation->id]);

        // dentisteA has patients_medical.view (so the timeline call itself
        // succeeds) but neither pma.view nor sante_mentale.view.
        $timelineAsDentiste = $this->actingAs($this->dentisteA)
            ->getJson("/api/patients/{$this->patientA->id}/timeline")
            ->assertOk()->json('data');

        $entries = collect($timelineAsDentiste)->where('type', 'consultation');
        $this->assertNull($entries->firstWhere('data.specialty_type', 'pma')['data']['specialty']);
        $this->assertNull($entries->firstWhere('data.specialty_type', 'sante_mentale')['data']['specialty']);

        // The authorized specialists still see the full nested record.
        $timelineAsPma = $this->actingAs($this->specialistePmaA)
            ->getJson("/api/patients/{$this->patientA->id}/timeline")
            ->assertOk()->json('data');
        $pmaEntry = collect($timelineAsPma)->where('type', 'consultation')->firstWhere('data.specialty_type', 'pma');
        $this->assertNotNull($pmaEntry['data']['specialty']);

        $timelineAsPsy = $this->actingAs($this->psychiatreA)
            ->getJson("/api/patients/{$this->patientA->id}/timeline")
            ->assertOk()->json('data');
        $smEntry = collect($timelineAsPsy)->where('type', 'consultation')->firstWhere('data.specialty_type', 'sante_mentale');
        $this->assertNotNull($smEntry['data']['specialty']);
    }

    public function test_oncologie_stats_endpoint_breaks_down_records_by_cancer_type(): void
    {
        $this->createOncoRecord(overrides: ['cancer_type' => 'sein', 'diagnosed_at' => '2026-08-05']);

        $patientA2 = Patient::factory()->for($this->structureA)->create();
        $this->createOncoRecord(overrides: [
            'patient_id' => $patientA2->id,
            'cancer_type' => 'poumon',
            'diagnosed_at' => '2026-08-06',
        ]);

        $stats = $this->actingAs($this->oncologueA)
            ->getJson('/api/oncologie-stats?from=2026-08-01&to=2026-08-31')
            ->assertOk()->json('data');

        $this->assertSame(2, $stats['total_records']);
        $this->assertSame(1, $stats['by_cancer_type']['sein']);
        $this->assertSame(1, $stats['by_cancer_type']['poumon']);
    }

    public function test_soins_domicile_stats_endpoint_counts_visits_in_a_period(): void
    {
        $record = $this->createHomeCareRecord();

        $this->actingAs($this->infirmierDomicileA)->postJson("/api/home-care-records/{$record['id']}/visits", [
            'care_type' => 'pansement',
            'visit_datetime' => '2026-08-05 09:00:00',
        ])->assertCreated();

        $this->actingAs($this->infirmierDomicileA)->postJson("/api/home-care-records/{$record['id']}/visits", [
            'care_type' => 'injection',
            'visit_datetime' => '2026-08-12 09:00:00',
        ])->assertCreated();

        $stats = $this->actingAs($this->infirmierDomicileA)
            ->getJson('/api/soins-domicile-stats?from=2026-08-01&to=2026-08-31')
            ->assertOk()->json('data');

        $this->assertSame(2, $stats['total_visits']);
    }

    // --- Permissions croisées entre spécialités -------------------------------

    public function test_a_role_cannot_act_outside_its_own_step_4b_specialty_module(): void
    {
        $this->actingAs($this->ophtalmologueA)->postJson('/api/cardio-records', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'examined_at' => '2026-08-01',
        ])->assertForbidden();

        $this->actingAs($this->cardiologueA)->postJson('/api/onco-records', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'cancer_type' => 'sein',
        ])->assertForbidden();
    }
}
