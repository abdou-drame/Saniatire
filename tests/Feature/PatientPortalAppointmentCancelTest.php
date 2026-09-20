<?php

namespace Tests\Feature;

use App\Domain\Appointment\Events\RendezVousAnnule;
use App\Domain\Appointment\Models\Appointment;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PatientPortalAppointmentCancelTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Patient $patientA;

    private Patient $otherPatientA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->structureA = Structure::factory()->create();
        $this->patientA = Patient::factory()->withPortalActivated()->for($this->structureA)->create();
        $this->otherPatientA = Patient::factory()->withPortalActivated()->for($this->structureA)->create();
    }

    public function test_a_patient_can_cancel_their_own_upcoming_appointment(): void
    {
        Event::fake([RendezVousAnnule::class]);

        $practitioner = User::factory()->for($this->structureA)->create();
        $appointment = Appointment::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $practitioner->id,
            'status' => 'confirme',
        ]);

        $response = $this->actingAs($this->patientA, 'patient')
            ->postJson("/api/portail-patient/rendez-vous/{$appointment->id}/annuler")
            ->assertOk();

        $response->assertJsonPath('data.status', 'annule');
        $this->assertSame('annule', $appointment->fresh()->status);

        Event::assertDispatched(RendezVousAnnule::class, fn (RendezVousAnnule $event) => $event->appointment->id === $appointment->id);
    }

    public function test_a_patient_cannot_cancel_another_patients_appointment(): void
    {
        $practitioner = User::factory()->for($this->structureA)->create();
        $appointment = Appointment::factory()->for($this->structureA)->create([
            'patient_id' => $this->otherPatientA->id,
            'practitioner_id' => $practitioner->id,
            'status' => 'confirme',
        ]);

        $this->actingAs($this->patientA, 'patient')
            ->postJson("/api/portail-patient/rendez-vous/{$appointment->id}/annuler")
            ->assertNotFound();

        $this->assertSame('confirme', $appointment->fresh()->status);
    }

    public function test_a_patient_cannot_cancel_an_appointment_from_another_structure(): void
    {
        $structureB = Structure::factory()->create();
        $patientB = Patient::factory()->withPortalActivated()->for($structureB)->create();
        $practitionerB = User::factory()->for($structureB)->create();

        $appointmentB = Appointment::factory()->for($structureB)->create([
            'patient_id' => $patientB->id,
            'practitioner_id' => $practitionerB->id,
            'status' => 'confirme',
        ]);

        $this->actingAs($this->patientA, 'patient')
            ->postJson("/api/portail-patient/rendez-vous/{$appointmentB->id}/annuler")
            ->assertNotFound();

        $this->assertSame('confirme', $appointmentB->fresh()->status);
    }
}
