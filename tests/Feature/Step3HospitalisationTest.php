<?php

namespace Tests\Feature;

use App\Domain\Hospitalisation\Models\Bed;
use App\Domain\Hospitalisation\Models\Ward;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Step3HospitalisationTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Structure $structureB;

    private Site $siteA;

    private User $medecinA;

    private User $infirmierA;

    private User $secretaireA;

    private Patient $patientA;

    private Ward $wardA;

    private Bed $bedA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structureA = Structure::factory()->create();
        $this->structureB = Structure::factory()->create();
        $this->siteA = Site::factory()->for($this->structureA)->create();

        $this->medecinA = User::factory()->for($this->structureA)->create();
        $this->medecinA->assignRole('medecin');

        $this->infirmierA = User::factory()->for($this->structureA)->create();
        $this->infirmierA->assignRole('infirmier');

        $this->secretaireA = User::factory()->for($this->structureA)->create();
        $this->secretaireA->assignRole('secretaire');

        $this->patientA = Patient::factory()->for($this->structureA)->create();

        $this->wardA = Ward::factory()->for($this->structureA)->for($this->siteA)->create();
        $this->bedA = Bed::factory()->for($this->structureA)->for($this->siteA)->for($this->wardA)->create(['status' => 'libre']);
    }

    private function admit(Patient $patient, Bed $bed): array
    {
        return $this->actingAs($this->medecinA)->postJson('/api/hospitalizations', [
            'site_id' => $this->siteA->id,
            'patient_id' => $patient->id,
            'bed_id' => $bed->id,
            'attending_physician_id' => $this->medecinA->id,
            'admission_reason' => 'Surveillance post-opératoire',
        ])->assertCreated()->json('data');
    }

    // --- Workflow complet ------------------------------------------------

    public function test_full_hospitalization_workflow_from_admission_to_discharge(): void
    {
        $hospitalization = $this->admit($this->patientA, $this->bedA);

        $this->assertSame('occupe', $this->bedA->fresh()->status);
        $this->assertSame('en_cours', $hospitalization['status']);

        $this->actingAs($this->infirmierA)->postJson("/api/hospitalizations/{$hospitalization['id']}/daily-notes", [
            'care_administered' => 'Pansement refait',
            'observations' => 'Patient stable',
        ])->assertCreated();

        $this->actingAs($this->medecinA)
            ->postJson("/api/hospitalizations/{$hospitalization['id']}/discharge", [
                'discharge_summary' => 'Sortie sans complication.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'sorti');

        $this->assertSame('libre', $this->bedA->fresh()->status);
    }

    // --- Règle bloquante : lit déjà occupé ----------------------------------

    public function test_a_second_patient_cannot_be_admitted_to_an_already_occupied_bed(): void
    {
        $this->admit($this->patientA, $this->bedA);

        $secondPatient = Patient::factory()->for($this->structureA)->create();

        $this->actingAs($this->medecinA)->postJson('/api/hospitalizations', [
            'site_id' => $this->siteA->id,
            'patient_id' => $secondPatient->id,
            'bed_id' => $this->bedA->id,
            'attending_physician_id' => $this->medecinA->id,
            'admission_reason' => 'Autre motif',
        ])->assertStatus(422);
    }

    public function test_discharge_requires_a_summary(): void
    {
        $hospitalization = $this->admit($this->patientA, $this->bedA);

        $this->actingAs($this->medecinA)
            ->postJson("/api/hospitalizations/{$hospitalization['id']}/discharge", [])
            ->assertStatus(422);
    }

    // --- Occupation ---------------------------------------------------------

    public function test_occupancy_stats_reflect_admissions(): void
    {
        $this->admit($this->patientA, $this->bedA);

        $response = $this->actingAs($this->medecinA)->getJson('/api/wards/occupancy-stats')->assertOk();

        $wardStats = collect($response->json('data'))->firstWhere('ward_id', $this->wardA->id);

        $this->assertSame(1, $wardStats['total_beds']);
        $this->assertSame(1, $wardStats['occupied_beds']);
        $this->assertEquals(100.0, $wardStats['occupancy_rate']);
    }

    // --- Permissions ---------------------------------------------------------

    public function test_secretary_cannot_admit_a_patient(): void
    {
        $this->actingAs($this->secretaireA)->postJson('/api/hospitalizations', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'bed_id' => $this->bedA->id,
            'attending_physician_id' => $this->medecinA->id,
            'admission_reason' => 'Motif',
        ])->assertForbidden();
    }

    public function test_infirmier_cannot_discharge_a_patient(): void
    {
        $hospitalization = $this->admit($this->patientA, $this->bedA);

        $this->actingAs($this->infirmierA)
            ->postJson("/api/hospitalizations/{$hospitalization['id']}/discharge", [
                'discharge_summary' => 'Tentative non autorisée.',
            ])
            ->assertForbidden();
    }

    // --- Isolation multi-tenant ----------------------------------------------

    public function test_a_hospitalization_is_invisible_to_another_structure(): void
    {
        $hospitalization = $this->admit($this->patientA, $this->bedA);

        $medecinB = User::factory()->for($this->structureB)->create();
        $medecinB->assignRole('medecin');

        $this->actingAs($medecinB)->getJson("/api/hospitalizations/{$hospitalization['id']}")->assertNotFound();
    }
}
