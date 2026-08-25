<?php

namespace Tests\Feature;

use App\Domain\Notification\Models\Notification;
use App\Domain\Patient\Models\Patient;
use App\Domain\Qualite\Models\PatientSatisfactionSurvey;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientSatisfactionSurveyTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Patient $patientA;

    private User $secretaireA;

    private User $caissierA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(NotificationTemplateSeeder::class);

        $this->structureA = Structure::factory()->create();
        // Email explicite : PatientFactory utilise fake()->optional()->safeEmail(),
        // qui vaut null environ une fois sur deux. Sans ça, le test de
        // notification (canal email par défaut) est flaky : quand l'email
        // tire null, EmailChannel::resolveDestinataire() ne trouve aucun
        // destinataire et NotificationDispatcher ignore silencieusement l'envoi.
        $this->patientA = Patient::factory()->for($this->structureA)->create(['email' => 'patienta@example.test']);

        $this->secretaireA = User::factory()->for($this->structureA)->create();
        $this->secretaireA->assignRole('secretaire');

        $this->caissierA = User::factory()->for($this->structureA)->create();
        $this->caissierA->assignRole('caissier');
    }

    // --- Validation de la note (1-10) --------------------------------------

    public function test_a_satisfaction_note_out_of_range_is_rejected(): void
    {
        $this->actingAs($this->secretaireA)->postJson('/api/patient-satisfaction-surveys', [
            'patient_id' => $this->patientA->id,
            'service' => 'consultation',
            'note' => 15,
        ])->assertStatus(422);

        $this->assertDatabaseCount('patient_satisfaction_surveys', 0);
    }

    public function test_a_valid_satisfaction_survey_is_created(): void
    {
        $response = $this->actingAs($this->secretaireA)->postJson('/api/patient-satisfaction-surveys', [
            'patient_id' => $this->patientA->id,
            'service' => 'consultation',
            'note' => 9,
            'commentaire' => 'Très satisfait',
        ])->assertCreated();

        $this->assertEquals(9, $response->json('data.note'));

        $survey = PatientSatisfactionSurvey::first();
        $this->assertEquals(9, $survey->note);
        $this->assertEquals($this->structureA->id, $survey->structure_id);
    }

    // --- Déclenchement de l'envoi de l'enquête -----------------------------

    public function test_sending_a_satisfaction_invitation_dispatches_a_notification(): void
    {
        $this->actingAs($this->secretaireA)->postJson('/api/patient-satisfaction-surveys/send-invitation', [
            'patient_id' => $this->patientA->id,
            'service' => 'laboratoire',
        ])->assertOk();

        $this->assertTrue(
            Notification::where('type_evenement', 'enquete_satisfaction')
                ->where('notifiable_type', Patient::class)
                ->where('notifiable_id', $this->patientA->id)
                ->exists()
        );
    }

    // --- Permissions --------------------------------------------------

    public function test_a_cashier_cannot_create_a_satisfaction_survey(): void
    {
        $this->actingAs($this->caissierA)->postJson('/api/patient-satisfaction-surveys', [
            'patient_id' => $this->patientA->id,
            'note' => 8,
        ])->assertForbidden();
    }
}
