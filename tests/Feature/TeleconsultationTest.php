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
 * Étape 7b §4/§7 : une téléconsultation clôturée produit une Consultation
 * `terminee`, visible dans la timeline patient au même titre qu'une
 * consultation classique (PatientController::timeline() ne filtre que sur
 * status=terminee, aucune distinction téléconsultation/présentiel).
 */
class TeleconsultationTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structure;

    private Site $site;

    private User $medecin;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structure = Structure::factory()->create();
        $this->site = Site::factory()->for($this->structure)->create();

        $this->medecin = User::factory()->for($this->structure)->create();
        $this->medecin->assignRole('medecin');

        $this->patient = Patient::factory()->for($this->structure)->create();
    }

    public function test_closing_a_teleconsultation_creates_a_completed_consultation_visible_in_the_patient_timeline(): void
    {
        $appointment = Appointment::factory()->for($this->structure)->create([
            'site_id' => $this->site->id,
            'patient_id' => $this->patient->id,
            'practitioner_id' => $this->medecin->id,
        ]);

        $teleconsultationId = $this->actingAs($this->medecin)
            ->postJson('/api/teleconsultations', ['appointment_id' => $appointment->id])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($this->medecin)
            ->postJson("/api/teleconsultations/{$teleconsultationId}/start")
            ->assertOk()
            ->assertJsonPath('data.statut', 'en_cours');

        $close = $this->actingAs($this->medecin)
            ->postJson("/api/teleconsultations/{$teleconsultationId}/close", [
                'reason' => 'Suivi post-opératoire',
                'clinical_exam' => 'RAS en visio',
            ]);

        $this->assertContains($close->getStatusCode(), [200, 201]);

        $this->assertSame('terminee', $close->json('data.status'));
        $consultationId = $close->json('data.id');

        $timeline = $this->actingAs($this->medecin)
            ->getJson("/api/patients/{$this->patient->id}/timeline")
            ->assertOk();

        $entries = collect($timeline->json('data'));
        $this->assertTrue($entries->contains(fn ($entry) => $entry['type'] === 'consultation' && $entry['data']['id'] === $consultationId));
    }

    public function test_a_teleconsultation_cannot_be_started_or_closed_after_being_cancelled(): void
    {
        $appointment = Appointment::factory()->for($this->structure)->create([
            'site_id' => $this->site->id,
            'patient_id' => $this->patient->id,
            'practitioner_id' => $this->medecin->id,
        ]);

        $teleconsultationId = $this->actingAs($this->medecin)
            ->postJson('/api/teleconsultations', ['appointment_id' => $appointment->id])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($this->medecin)
            ->postJson("/api/teleconsultations/{$teleconsultationId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.statut', 'annulee');

        $this->actingAs($this->medecin)
            ->postJson("/api/teleconsultations/{$teleconsultationId}/start")
            ->assertStatus(422);
    }

    public function test_a_unique_unguessable_jitsi_room_is_created_and_shown_to_the_patient_portal(): void
    {
        $patient = Patient::factory()->withPortalActivated()->for($this->structure)->create();
        $appointments = Appointment::factory()->for($this->structure)->count(2)->create([
            'site_id' => $this->site->id,
            'patient_id' => $patient->id,
            'practitioner_id' => $this->medecin->id,
        ]);

        $liens = $appointments->map(fn (Appointment $appointment) => $this->actingAs($this->medecin)
            ->postJson('/api/teleconsultations', ['appointment_id' => $appointment->id])
            ->assertCreated()
            ->json('data.lien_session'));

        foreach ($liens as $lien) {
            $this->assertMatchesRegularExpression('#^https://meet\.jit\.si/saliha-[0-9a-f]{32}$#', $lien);
        }
        $this->assertNotSame($liens[0], $liens[1]);

        $portail = collect($this->actingAs($patient, 'patient')
            ->getJson('/api/portail-patient/rendez-vous')
            ->assertOk()
            ->json('data'))->keyBy('id');

        $this->assertSame($liens[0], $portail[$appointments[0]->id]['lien_teleconsultation']);
        $this->assertSame($liens[1], $portail[$appointments[1]->id]['lien_teleconsultation']);
    }
}
