<?php

namespace Tests\Feature;

use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_first_name_last_name_or_patient_number(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $structure = Structure::factory()->create();
        $medecin = User::factory()->for($structure)->create();
        $medecin->assignRole('medecin');

        $match = Patient::factory()->for($structure)->create([
            'first_name' => 'Awa',
            'last_name' => 'Koné',
        ]);

        Patient::factory()->for($structure)->create([
            'first_name' => 'Ibrahim',
            'last_name' => 'Sanogo',
        ]);

        $response = $this->actingAs($medecin)->getJson('/api/patients?search=koné');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $match->id);
    }

    public function test_search_by_patient_number_returns_exact_patient(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $structure = Structure::factory()->create();
        $medecin = User::factory()->for($structure)->create();
        $medecin->assignRole('medecin');

        $match = Patient::factory()->for($structure)->create();

        Patient::factory()->for($structure)->count(3)->create();

        $response = $this->actingAs($medecin)->getJson('/api/patients?search='.$match->patient_number);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $match->id);
    }

    public function test_no_search_param_returns_all_patients_unfiltered(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $structure = Structure::factory()->create();
        $medecin = User::factory()->for($structure)->create();
        $medecin->assignRole('medecin');

        Patient::factory()->for($structure)->count(4)->create();

        $response = $this->actingAs($medecin)->getJson('/api/patients');

        $response->assertOk();
        $response->assertJsonCount(4, 'data');
    }
}
