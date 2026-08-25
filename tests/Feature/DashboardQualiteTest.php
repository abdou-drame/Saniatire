<?php

namespace Tests\Feature;

use App\Domain\Patient\Models\Patient;
use App\Domain\Qualite\Models\Complaint;
use App\Domain\Qualite\Models\PatientSatisfactionSurvey;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardQualiteTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Patient $patientA;

    private User $directionA;

    private User $infirmierA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structureA = Structure::factory()->create();
        $this->patientA = Patient::factory()->for($this->structureA)->create();

        $this->directionA = User::factory()->for($this->structureA)->create();
        $this->directionA->assignRole('direction');

        $this->infirmierA = User::factory()->for($this->structureA)->create();
        $this->infirmierA->assignRole('infirmier');
    }

    // --- Score moyen de satisfaction, avec filtre par service --------------

    public function test_average_satisfaction_score_is_computed_and_filterable_by_service(): void
    {
        $from = '2026-08-01';
        $to = '2026-08-31';

        PatientSatisfactionSurvey::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id, 'service' => 'consultation', 'note' => 8, 'date' => '2026-08-10',
        ]);
        PatientSatisfactionSurvey::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id, 'service' => 'consultation', 'note' => 6, 'date' => '2026-08-11',
        ]);
        PatientSatisfactionSurvey::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id, 'service' => 'laboratoire', 'note' => 10, 'date' => '2026-08-12',
        ]);
        // Hors période : exclu.
        PatientSatisfactionSurvey::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id, 'service' => 'consultation', 'note' => 1, 'date' => '2026-01-01',
        ]);

        $global = $this->actingAs($this->directionA)
            ->getJson("/api/dashboards/qualite?from={$from}&to={$to}")
            ->assertOk();

        // (8 + 6 + 10) / 3 = 8.
        $this->assertEquals(8.0, $global->json('score_moyen_satisfaction'));

        $filtered = $this->actingAs($this->directionA)
            ->getJson("/api/dashboards/qualite?from={$from}&to={$to}&service=consultation")
            ->assertOk();

        // (8 + 6) / 2 = 7.
        $this->assertEquals(7.0, $filtered->json('score_moyen_satisfaction'));
    }

    // --- Répartition des réclamations et délai moyen de résolution -------

    public function test_complaint_breakdown_and_average_resolution_delay_are_computed(): void
    {
        $from = '2026-08-01';
        $to = '2026-08-31';

        Complaint::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id, 'motif' => 'attente', 'statut' => 'ouverte', 'created_at' => '2026-08-05',
        ]);
        Complaint::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id, 'motif' => 'attente', 'statut' => 'en_cours', 'created_at' => '2026-08-06',
        ]);
        Complaint::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id, 'motif' => 'facturation', 'statut' => 'resolue',
            'created_at' => '2026-08-01 08:00:00', 'resolved_at' => '2026-08-03 08:00:00',
        ]);
        Complaint::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id, 'motif' => 'facturation', 'statut' => 'resolue',
            'created_at' => '2026-08-02 08:00:00', 'resolved_at' => '2026-08-03 08:00:00',
        ]);

        $response = $this->actingAs($this->directionA)
            ->getJson("/api/dashboards/qualite?from={$from}&to={$to}")
            ->assertOk();

        $parMotif = collect($response->json('reclamations.par_motif'))->keyBy('motif');
        $this->assertEquals(2, $parMotif['attente']['total']);
        $this->assertEquals(2, $parMotif['facturation']['total']);

        $parStatut = collect($response->json('reclamations.par_statut'))->keyBy('statut');
        $this->assertEquals(1, $parStatut['ouverte']['total']);
        $this->assertEquals(1, $parStatut['en_cours']['total']);
        $this->assertEquals(2, $parStatut['resolue']['total']);

        // Réclamation 1 : 48h (2026-08-01 08:00 -> 2026-08-03 08:00).
        // Réclamation 2 : 24h (2026-08-02 08:00 -> 2026-08-03 08:00).
        // Moyenne : (48 + 24) / 2 = 36h.
        $this->assertEquals(36.0, $response->json('delai_moyen_resolution_heures'));
    }

    // --- Permissions --------------------------------------------------

    public function test_a_nurse_cannot_access_the_quality_dashboard(): void
    {
        $this->actingAs($this->infirmierA)
            ->getJson('/api/dashboards/qualite')
            ->assertForbidden();
    }
}
