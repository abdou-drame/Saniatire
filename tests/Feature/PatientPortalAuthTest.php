<?php

namespace Tests\Feature;

use App\Domain\Appointment\Models\Appointment;
use App\Domain\Facturation\Models\Invoice;
use App\Domain\Patient\Models\Patient;
use App\Domain\Rh\Models\LeaveRequest;
use App\Domain\Rh\Models\WorkSchedule;
use App\Domain\Shared\Auth\PortalActivationService;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Étape 7b §1/§2/§7 : étanchéité du guard `patient` (rejeté sur les routes
 * staff et vice-versa) + isolation stricte de chaque patient sur son propre
 * portail (l'identité vient uniquement de $request->user(), jamais d'un id).
 */
class PatientPortalAuthTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structure;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->structure = Structure::factory()->create();
        $this->site = Site::factory()->for($this->structure)->create();
    }

    public function test_full_activation_login_and_own_data_access_flow(): void
    {
        $patient = Patient::factory()->for($this->structure)->create(['email' => 'patient@example.com']);

        $token = app(PortalActivationService::class)->createFor($patient);

        $this->postJson('/api/portail-patient/activer', [
            'token' => $token,
            'password' => 'un-mot-de-passe-solide',
            'password_confirmation' => 'un-mot-de-passe-solide',
        ])->assertOk();

        $patient->refresh();
        $this->assertNotNull($patient->portal_activated_at);

        $login = $this->postJson('/api/portail-patient/login', [
            'email' => 'patient@example.com',
            'password' => 'un-mot-de-passe-solide',
        ]);

        $login->assertOk();
        $apiToken = $login->json('token');
        $this->assertNotEmpty($apiToken);

        $this->withHeader('Authorization', "Bearer {$apiToken}")
            ->getJson('/api/portail-patient/me')
            ->assertOk()
            ->assertJsonPath('data.id', $patient->id);
    }

    public function test_a_staff_token_is_rejected_on_patient_portal_routes(): void
    {
        $user = User::factory()->for($this->structure)->create();
        $staffToken = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->getJson('/api/portail-patient/me')
            ->assertUnauthorized();
    }

    public function test_a_patient_token_is_rejected_on_staff_routes(): void
    {
        $patient = Patient::factory()->withPortalActivated()->for($this->structure)->create();
        $patientToken = $patient->createToken('patient-portal')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$patientToken}")
            ->getJson('/api/patients')
            ->assertUnauthorized();
    }

    public function test_a_patient_only_sees_their_own_appointments_never_another_patients(): void
    {
        $practitioner = User::factory()->for($this->structure)->create();

        $patientA = Patient::factory()->withPortalActivated()->for($this->structure)->create();
        $patientB = Patient::factory()->withPortalActivated()->for($this->structure)->create();

        Appointment::factory()->for($this->structure)->create([
            'site_id' => $this->site->id,
            'patient_id' => $patientA->id,
            'practitioner_id' => $practitioner->id,
        ]);
        Appointment::factory()->for($this->structure)->create([
            'site_id' => $this->site->id,
            'patient_id' => $patientB->id,
            'practitioner_id' => $practitioner->id,
        ]);

        $response = $this->actingAs($patientA, 'patient')->getJson('/api/portail-patient/rendez-vous');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($patientA->id, $response->json('data.0.patient_id'));
    }

    public function test_an_online_appointment_cannot_be_booked_outside_the_practitioners_presence_planning(): void
    {
        $day = now()->addWeek()->startOfDay();
        $practitioner = User::factory()->for($this->structure)->create();

        WorkSchedule::factory()->for($this->structure)->create([
            'user_id' => $practitioner->id,
            'site_id' => $this->site->id,
            'jour_semaine' => $day->dayOfWeek,
            'date' => null,
            'heure_debut' => '08:00:00',
            'heure_fin' => '12:00:00',
            'type' => 'normal',
        ]);

        $patient = Patient::factory()->withPortalActivated()->for($this->structure)->create();

        $response = $this->actingAs($patient, 'patient')->postJson('/api/portail-patient/rendez-vous', [
            'site_id' => $this->site->id,
            'practitioner_id' => $practitioner->id,
            'starts_at' => $day->clone()->setTime(15, 0)->toDateTimeString(),
            'duration_minutes' => 30,
            'reason' => 'Consultation hors planning',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_an_online_appointment_cannot_be_booked_during_a_practitioners_validated_leave(): void
    {
        $leaveDay = now()->addWeeks(2)->startOfDay();
        $practitioner = User::factory()->for($this->structure)->create();

        LeaveRequest::factory()->for($this->structure)->create([
            'user_id' => $practitioner->id,
            'type' => 'conge_annuel',
            'date_debut' => $leaveDay->toDateString(),
            'date_fin' => $leaveDay->clone()->addDays(3)->toDateString(),
            'statut' => 'valide',
        ]);

        $patient = Patient::factory()->withPortalActivated()->for($this->structure)->create();

        $response = $this->actingAs($patient, 'patient')->postJson('/api/portail-patient/rendez-vous', [
            'site_id' => $this->site->id,
            'practitioner_id' => $practitioner->id,
            'starts_at' => $leaveDay->clone()->setTime(10, 0)->toDateTimeString(),
            'duration_minutes' => 30,
            'reason' => 'Consultation pendant congé',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('congé', $response->json('message'));
    }

    public function test_an_online_appointment_can_be_booked_inside_the_practitioners_normal_schedule(): void
    {
        $day = now()->addWeek()->startOfDay();
        $practitioner = User::factory()->for($this->structure)->create();

        WorkSchedule::factory()->for($this->structure)->create([
            'user_id' => $practitioner->id,
            'site_id' => $this->site->id,
            'jour_semaine' => $day->dayOfWeek,
            'date' => null,
            'heure_debut' => '08:00:00',
            'heure_fin' => '12:00:00',
            'type' => 'normal',
        ]);

        $patient = Patient::factory()->withPortalActivated()->for($this->structure)->create();

        $this->actingAs($patient, 'patient')->postJson('/api/portail-patient/rendez-vous', [
            'site_id' => $this->site->id,
            'practitioner_id' => $practitioner->id,
            'starts_at' => $day->clone()->setTime(9, 0)->toDateTimeString(),
            'duration_minutes' => 30,
            'reason' => 'Consultation dans le planning',
        ])->assertCreated();
    }

    public function test_a_patient_only_sees_their_own_invoices_never_another_patients(): void
    {
        $patientA = Patient::factory()->withPortalActivated()->for($this->structure)->create();
        $patientB = Patient::factory()->withPortalActivated()->for($this->structure)->create();

        Invoice::factory()->for($this->structure)->for($this->site)->create(['patient_id' => $patientA->id]);
        Invoice::factory()->for($this->structure)->for($this->site)->create(['patient_id' => $patientB->id]);

        $response = $this->actingAs($patientA, 'patient')->getJson('/api/portail-patient/factures');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($patientA->id, $response->json('data.0.patient_id'));
    }
}
