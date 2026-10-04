<?php

namespace Tests\Feature;

use App\Domain\Appointment\Models\Appointment;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit sécurité I8 : les règles exists: interrogeaient la base sans
 * TenantScope, donc un id d'une autre structure était accepté (voir
 * TenantAwareValidator).
 */
class CrossStructureReferenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Site $site;

    private Patient $patient;

    private Patient $foreignPatient;

    private Site $foreignSite;

    private User $foreignPractitioner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $structure = Structure::factory()->create();
        $foreign = Structure::factory()->create();

        $this->admin = User::factory()->for($structure)->create();
        $this->admin->assignRole('administrateur');
        $this->site = Site::factory()->for($structure)->create();
        $this->patient = Patient::factory()->for($structure)->create();

        $this->foreignPatient = Patient::factory()->for($foreign)->create();
        $this->foreignSite = Site::factory()->for($foreign)->create();
        $this->foreignPractitioner = User::factory()->for($foreign)->create();
    }

    private function appointmentPayload(array $overrides = []): array
    {
        return [
            'site_id' => $this->site->id,
            'patient_id' => $this->patient->id,
            'practitioner_id' => $this->admin->id,
            'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
            'duration_minutes' => 30,
            ...$overrides,
        ];
    }

    public function test_an_appointment_cannot_reference_another_structures_patient_site_or_practitioner(): void
    {
        $this->actingAs($this->admin)->postJson('/api/appointments', $this->appointmentPayload([
            'site_id' => $this->foreignSite->id,
            'patient_id' => $this->foreignPatient->id,
            'practitioner_id' => $this->foreignPractitioner->id,
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['site_id', 'patient_id', 'practitioner_id']);

        $this->assertDatabaseMissing('appointments', ['patient_id' => $this->foreignPatient->id]);
    }

    public function test_an_appointment_still_accepts_the_structures_own_references(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/appointments', $this->appointmentPayload())
            ->assertJsonMissingValidationErrors(['site_id', 'patient_id', 'practitioner_id']);
    }

    public function test_an_appointment_cannot_be_moved_to_another_structures_patient(): void
    {
        $appointment = Appointment::factory()->create([
            'structure_id' => $this->admin->structure_id,
            'site_id' => $this->site->id,
            'patient_id' => $this->patient->id,
            'practitioner_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->putJson("/api/appointments/{$appointment->id}", ['patient_id' => $this->foreignPatient->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['patient_id']);

        $this->assertSame($this->patient->id, $appointment->fresh()->patient_id);
    }

    public function test_other_records_cannot_reference_another_structures_patient(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/consultations', ['patient_id' => $this->foreignPatient->id])
            ->assertJsonValidationErrors(['patient_id']);

        $this->actingAs($this->admin)
            ->postJson('/api/invoices', ['patient_id' => $this->foreignPatient->id])
            ->assertJsonValidationErrors(['patient_id']);
    }

    public function test_a_user_cannot_be_attached_to_another_structures_site(): void
    {
        $this->actingAs($this->admin)->postJson('/api/users', [
            'first_name' => 'Awa',
            'last_name' => 'Diop',
            'email' => 'awa@clinique.test',
            'password' => 'password-1234',
            'role' => 'secretaire',
            'site_ids' => [$this->foreignSite->id],
        ])->assertUnprocessable()->assertJsonValidationErrors(['site_ids.0']);
    }
}
