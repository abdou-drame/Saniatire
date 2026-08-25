<?php

namespace Tests\Feature;

use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_creating_a_patient_via_the_api_is_recorded_in_the_audit_log(): void
    {
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create();
        $admin->assignRole('administrateur');

        $response = $this->actingAs($admin)->postJson('/api/patients', [
            'first_name' => 'Fatou',
            'last_name' => 'Diabaté',
            'sex' => 'F',
            'birth_date' => '1988-06-15',
        ]);
        $response->assertCreated();
        $patientId = $response->json('data.id');

        $activity = Activity::query()
            ->where('subject_type', Patient::class)
            ->where('subject_id', $patientId)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($activity, 'Expected a "created" activity log entry for the new patient.');
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertNotNull($activity->created_at);
    }

    public function test_updating_a_patient_via_the_api_records_old_and_new_values(): void
    {
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create();
        $admin->assignRole('administrateur');

        $patient = Patient::factory()->for($structure)->create(['phone' => '+225 01 00 00 00 00']);

        $this->actingAs($admin)
            ->putJson("/api/patients/{$patient->id}", [
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'sex' => $patient->sex,
                'birth_date' => $patient->birth_date->format('Y-m-d'),
                'phone' => '+225 09 99 99 99 99',
            ])
            ->assertOk();

        $activity = Activity::query()
            ->where('subject_type', Patient::class)
            ->where('subject_id', $patient->id)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame('+225 01 00 00 00 00', $activity->properties['old']['phone']);
        $this->assertSame('+225 09 99 99 99 99', $activity->properties['attributes']['phone']);
    }

    public function test_deleting_a_patient_via_the_api_is_recorded_in_the_audit_log(): void
    {
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create();
        $admin->assignRole('administrateur');

        $patient = Patient::factory()->for($structure)->create();

        $this->actingAs($admin)
            ->deleteJson("/api/patients/{$patient->id}")
            ->assertNoContent();

        $activity = Activity::query()
            ->where('subject_type', Patient::class)
            ->where('subject_id', $patient->id)
            ->where('event', 'deleted')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($admin->id, $activity->causer_id);
    }
}
