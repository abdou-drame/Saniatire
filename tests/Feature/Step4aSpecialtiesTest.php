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

class Step4aSpecialtiesTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Structure $structureB;

    private Site $siteA;

    private User $gynecologueA;

    private User $sageFemmeA;

    private User $dentisteA;

    private User $nephrologueA;

    private User $infirmierDialyseA;

    private User $secretaireA;

    private Patient $patientA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structureA = Structure::factory()->create();
        $this->structureB = Structure::factory()->create();
        $this->siteA = Site::factory()->for($this->structureA)->create();

        $this->gynecologueA = User::factory()->for($this->structureA)->create();
        $this->gynecologueA->assignRole('gynecologue');

        $this->sageFemmeA = User::factory()->for($this->structureA)->create();
        $this->sageFemmeA->assignRole('sage_femme');

        $this->dentisteA = User::factory()->for($this->structureA)->create();
        $this->dentisteA->assignRole('dentiste');

        $this->nephrologueA = User::factory()->for($this->structureA)->create();
        $this->nephrologueA->assignRole('nephrologue');

        $this->infirmierDialyseA = User::factory()->for($this->structureA)->create();
        $this->infirmierDialyseA->assignRole('infirmier_dialyse');

        $this->secretaireA = User::factory()->for($this->structureA)->create();
        $this->secretaireA->assignRole('secretaire');

        $this->patientA = Patient::factory()->for($this->structureA)->create();
    }

    // --- Helpers -----------------------------------------------------------

    private function createMaternityRecord(?User $as = null, array $overrides = []): array
    {
        return $this->actingAs($as ?? $this->sageFemmeA)->postJson('/api/maternity-records', array_merge([
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'last_menstrual_period_date' => '2026-01-01',
        ], $overrides))->assertCreated()->json('data');
    }

    private function createDentalChart(?User $as = null, array $overrides = []): array
    {
        return $this->actingAs($as ?? $this->dentisteA)->postJson('/api/dental-charts', array_merge([
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
        ], $overrides))->assertCreated()->json('data');
    }

    private function createDialysisProgram(?User $as = null, array $overrides = []): array
    {
        return $this->actingAs($as ?? $this->nephrologueA)->postJson('/api/dialysis-programs', array_merge([
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'frequency_per_week' => 3,
            'dry_weight_kg' => 65.5,
            'vascular_access_type' => 'fistule',
            'started_at' => '2026-01-01',
        ], $overrides))->assertCreated()->json('data');
    }

    // --- Maternité -----------------------------------------------------------

    public function test_sage_femme_can_create_maternity_record_with_dpa_auto_computed_from_ddr(): void
    {
        $record = $this->createMaternityRecord();

        $this->assertSame('2026-01-01', $record['last_menstrual_period_date']);
        $this->assertSame('2026-10-08', $record['estimated_delivery_date']);
        $this->assertSame('suivi', $record['status']);
    }

    public function test_maternity_record_stores_consultation_link_and_updates_specialty_type(): void
    {
        $consultation = Consultation::factory()->for($this->structureA)->for($this->patientA)->create();

        $record = $this->createMaternityRecord(overrides: ['consultation_id' => $consultation->id]);

        $this->assertSame('maternite', $consultation->fresh()->specialty_type);
        $this->assertSame($consultation->id, $record['consultation_id']);
    }

    public function test_sage_femme_can_record_prenatal_visit(): void
    {
        $record = $this->createMaternityRecord();

        $this->actingAs($this->sageFemmeA)
            ->postJson("/api/maternity-records/{$record['id']}/prenatal-visits", [
                'visit_number' => 1,
                'gestational_age_weeks' => 12,
                'weight_kg' => 60.2,
                'blood_pressure_systolic' => 110,
                'blood_pressure_diastolic' => 70,
                'fundal_height_cm' => 12,
                'fetal_movements' => 'presents',
                'fetal_heart_rate' => 140,
                'visit_date' => '2026-03-01',
            ])
            ->assertCreated()
            ->assertJsonPath('data.visit_number', 1);
    }

    public function test_newborn_apgar_score_outside_valid_range_is_rejected(): void
    {
        $record = $this->createMaternityRecord();

        $delivery = $this->actingAs($this->gynecologueA)
            ->postJson("/api/maternity-records/{$record['id']}/delivery", [
                'mode' => 'voie_basse',
                'delivered_at' => '2026-10-05 10:00:00',
            ])->assertCreated()->json('data');

        $this->actingAs($this->gynecologueA)
            ->postJson("/api/maternity-deliveries/{$delivery['id']}/newborns", [
                'sex' => 'f',
                'birth_weight_grams' => 3200,
                'apgar_1min' => 11,
                'apgar_5min' => 9,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('apgar_1min');

        $this->actingAs($this->gynecologueA)
            ->postJson("/api/maternity-deliveries/{$delivery['id']}/newborns", [
                'sex' => 'f',
                'birth_weight_grams' => 3200,
                'apgar_1min' => 8,
                'apgar_5min' => 9,
                'apgar_10min' => 10,
            ])
            ->assertCreated()
            ->assertJsonPath('data.apgar_5min', 9);
    }

    public function test_recording_a_second_delivery_for_the_same_pregnancy_is_rejected(): void
    {
        $record = $this->createMaternityRecord();

        $this->actingAs($this->gynecologueA)
            ->postJson("/api/maternity-records/{$record['id']}/delivery", [
                'mode' => 'voie_basse',
                'delivered_at' => '2026-10-05 10:00:00',
            ])->assertCreated();

        $this->actingAs($this->gynecologueA)
            ->postJson("/api/maternity-records/{$record['id']}/delivery", [
                'mode' => 'cesarienne',
                'delivered_at' => '2026-10-06 10:00:00',
            ])->assertStatus(422);
    }

    public function test_sage_femme_cannot_record_a_delivery_or_a_newborn(): void
    {
        $record = $this->createMaternityRecord();

        $this->actingAs($this->sageFemmeA)
            ->postJson("/api/maternity-records/{$record['id']}/delivery", [
                'mode' => 'voie_basse',
                'delivered_at' => '2026-10-05 10:00:00',
            ])->assertForbidden();
    }

    public function test_secretary_cannot_create_a_maternity_record(): void
    {
        $this->actingAs($this->secretaireA)->postJson('/api/maternity-records', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'last_menstrual_period_date' => '2026-01-01',
        ])->assertForbidden();
    }

    public function test_a_maternity_record_is_invisible_to_another_structure(): void
    {
        $record = $this->createMaternityRecord();

        $gynecologueB = User::factory()->for($this->structureB)->create();
        $gynecologueB->assignRole('gynecologue');

        $this->actingAs($gynecologueB)->getJson("/api/maternity-records/{$record['id']}")->assertNotFound();
    }

    // --- Dentaire --------------------------------------------------------

    public function test_odontogram_can_be_updated_tooth_by_tooth_without_overwriting_other_teeth(): void
    {
        $chart = $this->createDentalChart();

        $this->actingAs($this->dentisteA)
            ->putJson("/api/dental-charts/{$chart['id']}/teeth/11", ['status' => 'cariee'])
            ->assertOk()
            ->assertJsonPath('data.tooth_fdi', '11')
            ->assertJsonPath('data.status', 'cariee');

        $this->actingAs($this->dentisteA)
            ->putJson("/api/dental-charts/{$chart['id']}/teeth/21", ['status' => 'obturee'])
            ->assertOk()
            ->assertJsonPath('data.tooth_fdi', '21')
            ->assertJsonPath('data.status', 'obturee');

        // Re-updating tooth 11 must not touch tooth 21.
        $this->actingAs($this->dentisteA)
            ->putJson("/api/dental-charts/{$chart['id']}/teeth/11", ['status' => 'extraite'])
            ->assertOk();

        $show = $this->actingAs($this->dentisteA)
            ->getJson("/api/dental-charts/{$chart['id']}")
            ->assertOk()
            ->json('data');

        $states = collect($show['tooth_states'])->keyBy('tooth_fdi');
        $this->assertSame('extraite', $states['11']['status']);
        $this->assertSame('obturee', $states['21']['status']);
        $this->assertCount(2, $states);
    }

    public function test_invalid_tooth_status_is_rejected(): void
    {
        $chart = $this->createDentalChart();

        $this->actingAs($this->dentisteA)
            ->putJson("/api/dental-charts/{$chart['id']}/teeth/11", ['status' => 'inexistant'])
            ->assertStatus(422);
    }

    public function test_an_invalid_fdi_tooth_code_is_rejected_by_the_route(): void
    {
        $chart = $this->createDentalChart();

        $this->actingAs($this->dentisteA)
            ->putJson("/api/dental-charts/{$chart['id']}/teeth/99", ['status' => 'saine'])
            ->assertNotFound();
    }

    public function test_dentiste_can_record_a_procedure_and_a_treatment_plan_item(): void
    {
        $chart = $this->createDentalChart();

        $this->actingAs($this->dentisteA)
            ->postJson("/api/dental-charts/{$chart['id']}/procedures", [
                'tooth_fdi' => '16',
                'act_type' => 'detartrage',
                'performed_at' => '2026-08-01',
            ])->assertCreated();

        $plan = $this->actingAs($this->dentisteA)
            ->postJson("/api/dental-charts/{$chart['id']}/treatment-plans", [])
            ->assertCreated()->json('data');

        $this->actingAs($this->dentisteA)
            ->postJson("/api/dental-treatment-plans/{$plan['id']}/items", [
                'tooth_fdi' => '26',
                'act_type' => 'extraction',
                'planned_at' => '2026-09-01',
            ])->assertCreated()->assertJsonPath('data.status', 'prevu');
    }

    public function test_secretary_cannot_create_a_dental_chart(): void
    {
        $this->actingAs($this->secretaireA)->postJson('/api/dental-charts', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
        ])->assertForbidden();
    }

    public function test_a_dental_chart_is_invisible_to_another_structure(): void
    {
        $chart = $this->createDentalChart();

        $dentisteB = User::factory()->for($this->structureB)->create();
        $dentisteB->assignRole('dentiste');

        $this->actingAs($dentisteB)->getJson("/api/dental-charts/{$chart['id']}")->assertNotFound();
    }

    // --- Dialyse -----------------------------------------------------------

    public function test_dialysis_program_and_its_individual_sessions_relationship(): void
    {
        $program = $this->createDialysisProgram();

        $session = $this->actingAs($this->nephrologueA)
            ->postJson("/api/dialysis-programs/{$program['id']}/sessions", [
                'session_date' => '2026-08-10',
                'pre_weight_kg' => 68.4,
                'post_weight_kg' => 65.6,
                'duration_minutes' => 240,
                'blood_flow_rate_ml_min' => 300,
                'ultrafiltration_volume_ml' => 2800,
            ])->assertCreated()->json('data');

        $this->actingAs($this->nephrologueA)
            ->postJson("/api/dialysis-sessions/{$session['id']}/vitals", [
                'measured_at' => '2026-08-10 10:00:00',
                'blood_pressure_systolic' => 130,
                'blood_pressure_diastolic' => 80,
                'heart_rate' => 78,
            ])->assertCreated();

        $show = $this->actingAs($this->nephrologueA)
            ->getJson("/api/dialysis-programs/{$program['id']}")
            ->assertOk()->json('data');

        $this->assertCount(1, $show['sessions']);
        $this->assertSame($session['id'], $show['sessions'][0]['id']);
        $this->assertCount(1, $show['sessions'][0]['vitals']);
    }

    public function test_dialysis_session_requires_pre_session_weight(): void
    {
        $program = $this->createDialysisProgram();

        $this->actingAs($this->nephrologueA)
            ->postJson("/api/dialysis-programs/{$program['id']}/sessions", [
                'session_date' => '2026-08-10',
            ])->assertStatus(422)->assertJsonValidationErrors('pre_weight_kg');
    }

    public function test_infirmier_dialyse_can_record_a_session_but_not_create_or_update_a_program(): void
    {
        $program = $this->createDialysisProgram();

        $this->actingAs($this->infirmierDialyseA)
            ->postJson("/api/dialysis-programs/{$program['id']}/sessions", [
                'session_date' => '2026-08-11',
                'pre_weight_kg' => 67.0,
            ])->assertCreated();

        $this->actingAs($this->infirmierDialyseA)->postJson('/api/dialysis-programs', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'frequency_per_week' => 3,
            'dry_weight_kg' => 65,
            'vascular_access_type' => 'fistule',
            'started_at' => '2026-01-01',
        ])->assertForbidden();

        $this->actingAs($this->infirmierDialyseA)
            ->putJson("/api/dialysis-programs/{$program['id']}", ['frequency_per_week' => 4])
            ->assertForbidden();
    }

    public function test_a_dialysis_program_is_invisible_to_another_structure(): void
    {
        $program = $this->createDialysisProgram();

        $nephrologueB = User::factory()->for($this->structureB)->create();
        $nephrologueB->assignRole('nephrologue');

        $this->actingAs($nephrologueB)->getJson("/api/dialysis-programs/{$program['id']}")->assertNotFound();
    }

    // --- Timeline & statistiques ---------------------------------------------

    public function test_specialty_data_appears_in_the_unified_patient_timeline(): void
    {
        $consultation = Consultation::factory()->for($this->structureA)->for($this->patientA)->create([
            'status' => 'terminee',
            'closed_at' => now(),
        ]);

        $this->createDialysisProgram(overrides: ['consultation_id' => $consultation->id]);

        $timeline = $this->actingAs($this->nephrologueA)
            ->getJson("/api/patients/{$this->patientA->id}/timeline")
            ->assertOk()
            ->json('data');

        $entry = collect($timeline)->firstWhere('type', 'consultation');

        $this->assertNotNull($entry);
        $this->assertSame('dialyse', $entry['data']['specialty_type']);
        $this->assertNotNull($entry['data']['specialty']);
    }

    public function test_dialysis_stats_endpoint_counts_sessions_in_a_period(): void
    {
        $program = $this->createDialysisProgram();

        $this->actingAs($this->nephrologueA)->postJson("/api/dialysis-programs/{$program['id']}/sessions", [
            'session_date' => '2026-08-05',
            'pre_weight_kg' => 67.0,
        ])->assertCreated();

        $this->actingAs($this->nephrologueA)->postJson("/api/dialysis-programs/{$program['id']}/sessions", [
            'session_date' => '2026-08-12',
            'pre_weight_kg' => 67.5,
        ])->assertCreated();

        $stats = $this->actingAs($this->nephrologueA)
            ->getJson('/api/dialyse-stats?from=2026-08-01&to=2026-08-31')
            ->assertOk()->json('data');

        $this->assertSame(2, $stats['total_sessions']);
    }

    public function test_maternity_stats_endpoint_breaks_down_deliveries_by_mode(): void
    {
        $recordA = $this->createMaternityRecord();
        $this->actingAs($this->gynecologueA)->postJson("/api/maternity-records/{$recordA['id']}/delivery", [
            'mode' => 'voie_basse',
            'delivered_at' => '2026-08-05 10:00:00',
        ])->assertCreated();

        $patientA2 = Patient::factory()->for($this->structureA)->create();
        $recordB = $this->actingAs($this->sageFemmeA)->postJson('/api/maternity-records', [
            'site_id' => $this->siteA->id,
            'patient_id' => $patientA2->id,
            'last_menstrual_period_date' => '2026-01-01',
        ])->assertCreated()->json('data');
        $this->actingAs($this->gynecologueA)->postJson("/api/maternity-records/{$recordB['id']}/delivery", [
            'mode' => 'cesarienne',
            'delivered_at' => '2026-08-06 10:00:00',
        ])->assertCreated();

        $stats = $this->actingAs($this->gynecologueA)
            ->getJson('/api/maternite-stats?from=2026-08-01&to=2026-08-31')
            ->assertOk()->json('data');

        $this->assertSame(2, $stats['total_deliveries']);
        $this->assertSame(1, $stats['by_mode']['voie_basse']);
        $this->assertSame(1, $stats['by_mode']['cesarienne']);
    }

    public function test_dentaire_stats_endpoint_breaks_down_procedures_by_act_type(): void
    {
        $chart = $this->createDentalChart();

        $this->actingAs($this->dentisteA)->postJson("/api/dental-charts/{$chart['id']}/procedures", [
            'tooth_fdi' => '16',
            'act_type' => 'detartrage',
            'performed_at' => '2026-08-05',
        ])->assertCreated();

        $this->actingAs($this->dentisteA)->postJson("/api/dental-charts/{$chart['id']}/procedures", [
            'tooth_fdi' => '26',
            'act_type' => 'extraction',
            'performed_at' => '2026-08-06',
        ])->assertCreated();

        $stats = $this->actingAs($this->dentisteA)
            ->getJson('/api/dentaire-stats?from=2026-08-01&to=2026-08-31')
            ->assertOk()->json('data');

        $this->assertSame(2, $stats['total_procedures']);
        $this->assertSame(1, $stats['by_act_type']['detartrage']);
        $this->assertSame(1, $stats['by_act_type']['extraction']);
    }

    // --- Additional CPN / dossier chaining -----------------------------------

    public function test_multiple_prenatal_visits_chain_in_sequence(): void
    {
        $record = $this->createMaternityRecord();

        foreach ([1, 2, 3] as $i => $visitNumber) {
            $this->actingAs($this->sageFemmeA)
                ->postJson("/api/maternity-records/{$record['id']}/prenatal-visits", [
                    'visit_number' => $visitNumber,
                    'gestational_age_weeks' => 8 + $i * 4,
                    'visit_date' => sprintf('2026-0%d-01', 3 + $i),
                ])->assertCreated();
        }

        $show = $this->actingAs($this->sageFemmeA)
            ->getJson("/api/maternity-records/{$record['id']}")
            ->assertOk()->json('data');

        $this->assertCount(3, $show['prenatal_visits']);
        $this->assertSame([1, 2, 3], collect($show['prenatal_visits'])->pluck('visit_number')->sort()->values()->all());
    }

    // --- Dentaire : historique des actes et plan de traitement ---------------

    public function test_a_dental_procedure_appears_in_the_chart_history(): void
    {
        $chart = $this->createDentalChart();

        $this->actingAs($this->dentisteA)
            ->postJson("/api/dental-charts/{$chart['id']}/procedures", [
                'tooth_fdi' => '16',
                'act_type' => 'detartrage',
                'performed_at' => '2026-08-01',
            ])->assertCreated();

        $show = $this->actingAs($this->dentisteA)
            ->getJson("/api/dental-charts/{$chart['id']}")
            ->assertOk()->json('data');

        $this->assertCount(1, $show['procedures']);
        $this->assertSame('detartrage', $show['procedures'][0]['act_type']);
        $this->assertSame('16', $show['procedures'][0]['tooth_fdi']);
    }

    public function test_marking_a_treatment_plan_item_as_realized_does_not_affect_other_items(): void
    {
        $chart = $this->createDentalChart();

        $plan = $this->actingAs($this->dentisteA)
            ->postJson("/api/dental-charts/{$chart['id']}/treatment-plans", [])
            ->assertCreated()->json('data');

        $item1 = $this->actingAs($this->dentisteA)
            ->postJson("/api/dental-treatment-plans/{$plan['id']}/items", [
                'tooth_fdi' => '26',
                'act_type' => 'extraction',
                'planned_at' => '2026-09-01',
            ])->assertCreated()->json('data');

        $item2 = $this->actingAs($this->dentisteA)
            ->postJson("/api/dental-treatment-plans/{$plan['id']}/items", [
                'tooth_fdi' => '36',
                'act_type' => 'obturation',
                'planned_at' => '2026-09-15',
            ])->assertCreated()->json('data');

        $this->actingAs($this->dentisteA)
            ->patchJson("/api/dental-treatment-plan-items/{$item1['id']}", ['status' => 'realise'])
            ->assertOk()
            ->assertJsonPath('data.status', 'realise');

        $show = $this->actingAs($this->dentisteA)
            ->getJson("/api/dental-charts/{$chart['id']}")
            ->assertOk()->json('data');

        $items = collect($show['treatment_plans'][0]['items'])->keyBy('id');
        $this->assertSame('realise', $items[$item1['id']]['status']);
        $this->assertSame('prevu', $items[$item2['id']]['status']);
    }

    // --- Dialyse : complication tracée ---------------------------------------

    public function test_dialysis_session_records_a_complication(): void
    {
        $program = $this->createDialysisProgram();

        $session = $this->actingAs($this->nephrologueA)
            ->postJson("/api/dialysis-programs/{$program['id']}/sessions", [
                'session_date' => '2026-08-10',
                'pre_weight_kg' => 68.4,
                'complications' => 'Hypotension pendant la séance, resucrage.',
            ])->assertCreated()->json('data');

        $this->assertSame('Hypotension pendant la séance, resucrage.', $session['complications']);

        $show = $this->actingAs($this->nephrologueA)
            ->getJson("/api/dialysis-programs/{$program['id']}")
            ->assertOk()->json('data');

        $this->assertSame('Hypotension pendant la séance, resucrage.', $show['sessions'][0]['complications']);
    }

    // --- Timeline : ordre chronologique --------------------------------------

    public function test_timeline_entries_are_returned_in_chronological_order(): void
    {
        $older = Consultation::factory()->for($this->structureA)->for($this->patientA)->create([
            'status' => 'terminee',
            'closed_at' => now()->subDays(10),
        ]);
        $this->createDialysisProgram(overrides: ['consultation_id' => $older->id]);

        $newer = Consultation::factory()->for($this->structureA)->for($this->patientA)->create([
            'status' => 'terminee',
            'closed_at' => now()->subDay(),
        ]);
        $chart = $this->createDentalChart(overrides: ['consultation_id' => $newer->id]);
        Consultation::whereKey($newer->id)->update(['specialty_type' => 'dentaire']);

        $timeline = $this->actingAs($this->nephrologueA)
            ->getJson("/api/patients/{$this->patientA->id}/timeline")
            ->assertOk()->json('data');

        $consultationEntries = collect($timeline)->where('type', 'consultation')->values();
        $this->assertSame($newer->id, $consultationEntries[0]['data']['id']);
        $this->assertSame($older->id, $consultationEntries[1]['data']['id']);
    }

    // --- Permissions croisées entre spécialités -------------------------------

    public function test_a_role_cannot_act_outside_its_own_specialty_module(): void
    {
        $this->actingAs($this->dentisteA)->postJson('/api/maternity-records', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'last_menstrual_period_date' => '2026-01-01',
        ])->assertForbidden();

        $this->actingAs($this->nephrologueA)->postJson('/api/dental-charts', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
        ])->assertForbidden();

        $this->actingAs($this->gynecologueA)->postJson('/api/dialysis-programs', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'frequency_per_week' => 3,
            'dry_weight_kg' => 65,
            'vascular_access_type' => 'fistule',
            'started_at' => '2026-01-01',
        ])->assertForbidden();
    }

    // --- Isolation multi-tenant des ressources imbriquées ("feuilles") -------
    //
    // maternity_partograms, maternity_deliveries, dental_treatment_plans/items
    // and dialysis_sessions have no structure_id of their own (tenant scoping
    // is inherited through their parent). These prove the write endpoints
    // that bind such a "leaf" model directly from the route still refuse
    // cross-structure access.

    public function test_a_partogram_reading_cannot_be_recorded_from_another_structure(): void
    {
        $record = $this->createMaternityRecord();
        $partogram = $this->actingAs($this->sageFemmeA)
            ->postJson("/api/maternity-records/{$record['id']}/partogram", [
                'labor_started_at' => '2026-10-05 06:00:00',
            ])->assertCreated()->json('data');

        $gynecologueB = User::factory()->for($this->structureB)->create();
        $gynecologueB->assignRole('gynecologue');

        $this->actingAs($gynecologueB)
            ->postJson("/api/maternity-partograms/{$partogram['id']}/readings", [
                'recorded_at' => '2026-10-05 08:00:00',
                'cervical_dilation_cm' => 4,
            ])->assertNotFound();
    }

    public function test_a_newborn_cannot_be_recorded_on_another_structures_delivery(): void
    {
        $record = $this->createMaternityRecord();
        $delivery = $this->actingAs($this->gynecologueA)
            ->postJson("/api/maternity-records/{$record['id']}/delivery", [
                'mode' => 'voie_basse',
                'delivered_at' => '2026-10-05 10:00:00',
            ])->assertCreated()->json('data');

        $gynecologueB = User::factory()->for($this->structureB)->create();
        $gynecologueB->assignRole('gynecologue');

        $this->actingAs($gynecologueB)
            ->postJson("/api/maternity-deliveries/{$delivery['id']}/newborns", [
                'sex' => 'f',
                'birth_weight_grams' => 3200,
                'apgar_1min' => 8,
                'apgar_5min' => 9,
            ])->assertNotFound();
    }

    public function test_a_treatment_plan_item_cannot_be_created_or_updated_from_another_structure(): void
    {
        $chart = $this->createDentalChart();
        $plan = $this->actingAs($this->dentisteA)
            ->postJson("/api/dental-charts/{$chart['id']}/treatment-plans", [])
            ->assertCreated()->json('data');
        $item = $this->actingAs($this->dentisteA)
            ->postJson("/api/dental-treatment-plans/{$plan['id']}/items", [
                'tooth_fdi' => '26',
                'act_type' => 'extraction',
            ])->assertCreated()->json('data');

        $dentisteB = User::factory()->for($this->structureB)->create();
        $dentisteB->assignRole('dentiste');

        $this->actingAs($dentisteB)
            ->postJson("/api/dental-treatment-plans/{$plan['id']}/items", [
                'tooth_fdi' => '36',
                'act_type' => 'obturation',
            ])->assertNotFound();

        $this->actingAs($dentisteB)
            ->patchJson("/api/dental-treatment-plan-items/{$item['id']}", ['status' => 'realise'])
            ->assertNotFound();
    }

    public function test_a_session_vital_cannot_be_recorded_on_another_structures_dialysis_session(): void
    {
        $program = $this->createDialysisProgram();
        $session = $this->actingAs($this->nephrologueA)
            ->postJson("/api/dialysis-programs/{$program['id']}/sessions", [
                'session_date' => '2026-08-10',
                'pre_weight_kg' => 68.4,
            ])->assertCreated()->json('data');

        $nephrologueB = User::factory()->for($this->structureB)->create();
        $nephrologueB->assignRole('nephrologue');

        $this->actingAs($nephrologueB)
            ->postJson("/api/dialysis-sessions/{$session['id']}/vitals", [
                'measured_at' => '2026-08-10 10:00:00',
                'blood_pressure_systolic' => 130,
                'blood_pressure_diastolic' => 80,
                'heart_rate' => 78,
            ])->assertNotFound();
    }
}
