<?php

namespace Tests\Feature;

use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientDuplicateDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_patient_with_a_matching_identity_triplet_surfaces_a_duplicate_warning(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create();
        $admin->assignRole('administrateur');

        $existing = Patient::factory()->for($structure)->create([
            'first_name' => 'Awa',
            'last_name' => 'Koné',
            'birth_date' => '1990-01-01',
        ]);

        $response = $this->actingAs($admin)->postJson('/api/patients', [
            'first_name' => 'Awa',
            'last_name' => 'Koné',
            'sex' => 'F',
            'birth_date' => '1990-01-01',
        ]);

        $response->assertCreated();
        $response->assertJsonCount(1, 'possible_duplicates');
        $response->assertJsonPath('possible_duplicates.0.id', $existing->id);
    }

    public function test_creating_a_patient_with_no_matching_identity_reports_no_duplicates(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create();
        $admin->assignRole('administrateur');

        Patient::factory()->for($structure)->create([
            'first_name' => 'Awa',
            'last_name' => 'Koné',
            'birth_date' => '1990-01-01',
        ]);

        $response = $this->actingAs($admin)->postJson('/api/patients', [
            'first_name' => 'Ibrahim',
            'last_name' => 'Sanogo',
            'sex' => 'M',
            'birth_date' => '1985-03-12',
        ]);

        $response->assertCreated();
        $response->assertJsonCount(0, 'possible_duplicates');
    }

    public function test_patient_numbers_are_unique_across_many_creations(): void
    {
        $structure = Structure::factory()->create();

        $patients = Patient::factory()->for($structure)->count(25)->create();

        $this->assertSame(
            $patients->count(),
            $patients->pluck('patient_number')->unique()->count()
        );
    }
}
