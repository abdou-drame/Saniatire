<?php

namespace Tests\Feature;

use App\Domain\Patient\Models\Patient;
use App\Domain\Prescripteur\Models\ExternalPrescriber;
use App\Domain\Qualite\Models\Complaint;
use App\Domain\Qualite\Models\PatientSatisfactionSurvey;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\Teleconsultation\Models\Teleconsultation;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;

    private Structure $structureA;

    private Structure $structureB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structureA = Structure::factory()->create();
        $this->structureB = Structure::factory()->create();

        $this->userA = User::factory()->for($this->structureA)->create();
        $this->userA->assignRole('administrateur');
    }

    public function test_user_cannot_list_patients_from_another_structure(): void
    {
        Patient::factory()->for($this->structureA)->count(2)->create();
        Patient::factory()->for($this->structureB)->count(3)->create();

        $response = $this->actingAs($this->userA)->getJson('/api/patients');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_cannot_view_a_patient_from_another_structure(): void
    {
        $foreignPatient = Patient::factory()->for($this->structureB)->create();

        $this->actingAs($this->userA)
            ->getJson("/api/patients/{$foreignPatient->id}")
            ->assertNotFound();
    }

    public function test_user_cannot_update_a_site_from_another_structure(): void
    {
        $foreignSite = Site::factory()->for($this->structureB)->create();

        $this->actingAs($this->userA)
            ->putJson("/api/sites/{$foreignSite->id}", ['name' => 'Hacked'])
            ->assertNotFound();

        $this->assertDatabaseHas('sites', ['id' => $foreignSite->id, 'name' => $foreignSite->name]);
    }

    public function test_user_cannot_view_another_structures_own_record(): void
    {
        $this->actingAs($this->userA)
            ->getJson("/api/structures/{$this->structureB->id}")
            ->assertNotFound();
    }

    public function test_created_patient_is_automatically_attached_to_the_authenticated_users_structure(): void
    {
        $response = $this->actingAs($this->userA)->postJson('/api/patients', [
            'first_name' => 'Awa',
            'last_name' => 'Koné',
            'sex' => 'F',
            'birth_date' => '1990-01-01',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('patients', [
            'id' => $response->json('data.id'),
            'structure_id' => $this->structureA->id,
        ]);
    }

    public function test_user_cannot_view_an_external_prescriber_from_another_structure(): void
    {
        $foreignPrescriber = ExternalPrescriber::factory()->for($this->structureB)->create();

        $this->actingAs($this->userA)
            ->getJson("/api/external-prescribers/{$foreignPrescriber->id}")
            ->assertNotFound();
    }

    public function test_user_cannot_view_a_teleconsultation_from_another_structure(): void
    {
        $foreignTeleconsultation = Teleconsultation::factory()->for($this->structureB)->create();

        $this->actingAs($this->userA)
            ->getJson("/api/teleconsultations/{$foreignTeleconsultation->id}")
            ->assertNotFound();
    }

    // --- Étape 8 : qualité/réclamations -----------------------------------

    public function test_user_cannot_see_a_patient_satisfaction_survey_from_another_structure(): void
    {
        PatientSatisfactionSurvey::factory()->for($this->structureA)->count(2)->create();
        $foreignSurvey = PatientSatisfactionSurvey::factory()->for($this->structureB)->create();

        $response = $this->actingAs($this->userA)->getJson('/api/patient-satisfaction-surveys');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($foreignSurvey->id));
    }

    public function test_user_cannot_view_a_complaint_from_another_structure(): void
    {
        $foreignComplaint = Complaint::factory()->for($this->structureB)->create();

        $this->actingAs($this->userA)
            ->getJson("/api/complaints/{$foreignComplaint->id}")
            ->assertNotFound();
    }
}
