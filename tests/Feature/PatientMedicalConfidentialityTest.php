<?php

namespace Tests\Feature;

use App\Domain\Patient\Models\Patient;
use App\Domain\Patient\Models\PatientMedicalInfo;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientMedicalConfidentialityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_role_without_medical_permission_sees_identity_but_not_medical_data(): void
    {
        $structure = Structure::factory()->create();
        $secretary = User::factory()->for($structure)->create();
        $secretary->assignRole('secretaire');

        $patient = Patient::factory()->for($structure)->has(PatientMedicalInfo::factory(), 'medicalInfo')->create();

        $response = $this->actingAs($secretary)->getJson("/api/patients/{$patient->id}");

        $response->assertOk();
        $response->assertJsonPath('data.first_name', $patient->first_name);
        $response->assertJsonMissing(['medical_info' => ['blood_group' => $patient->medicalInfo->blood_group]]);
        $this->assertNull($response->json('data.medical_info'));
    }

    public function test_a_role_with_medical_permission_sees_medical_data(): void
    {
        $structure = Structure::factory()->create();
        $doctor = User::factory()->for($structure)->create();
        $doctor->assignRole('medecin');

        $patient = Patient::factory()->for($structure)->has(PatientMedicalInfo::factory(), 'medicalInfo')->create();

        $response = $this->actingAs($doctor)->getJson("/api/patients/{$patient->id}");

        $response->assertOk();
        $this->assertNotNull($response->json('data.medical_info'));
        $response->assertJsonPath('data.medical_info.blood_group', $patient->medicalInfo->blood_group);
    }
}
