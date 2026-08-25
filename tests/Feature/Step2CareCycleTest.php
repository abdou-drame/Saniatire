<?php

namespace Tests\Feature;

use App\Domain\Appointment\Events\RendezVousAnnule;
use App\Domain\Appointment\Events\RendezVousCree;
use App\Domain\Appointment\Events\RendezVousModifie;
use App\Domain\Appointment\Models\Appointment;
use App\Domain\Consultation\Models\Consultation;
use App\Domain\Consultation\Models\ConsultationDiagnosis;
use App\Domain\Icd\Models\IcdCode;
use App\Domain\Icd\Models\IcdCodeMapping;
use App\Domain\Patient\Models\Patient;
use App\Domain\Queue\Models\QueueEntry;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class Step2CareCycleTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Structure $structureB;

    private Site $siteA;

    private User $medecinA;

    private User $secretaireA;

    private User $infirmierA;

    private Patient $patientA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structureA = Structure::factory()->create();
        $this->structureB = Structure::factory()->create();
        $this->siteA = Site::factory()->for($this->structureA)->create();

        $this->medecinA = User::factory()->for($this->structureA)->create();
        $this->medecinA->assignRole('medecin');

        $this->secretaireA = User::factory()->for($this->structureA)->create();
        $this->secretaireA->assignRole('secretaire');

        $this->infirmierA = User::factory()->for($this->structureA)->create();
        $this->infirmierA->assignRole('infirmier');

        $this->patientA = Patient::factory()->for($this->structureA)->create();
    }

    // --- Rendez-vous ------------------------------------------------

    public function test_an_appointment_cannot_be_created_when_the_practitioner_is_already_busy(): void
    {
        $startsAt = now()->addDay()->setTime(9, 0);

        Appointment::factory()->for($this->structureA)->create([
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA->id,
            'starts_at' => $startsAt,
            'duration_minutes' => 30,
            'status' => 'planifie',
        ]);

        $overlappingStart = $startsAt->clone()->addMinutes(15);

        $response = $this->actingAs($this->secretaireA)->postJson('/api/appointments', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA->id,
            'starts_at' => $overlappingStart->toDateTimeString(),
            'duration_minutes' => 30,
            'reason' => 'Consultation de suivi',
        ]);

        $response->assertStatus(422);
    }

    public function test_an_appointment_can_be_created_outside_an_existing_slot(): void
    {
        $startsAt = now()->addDay()->setTime(9, 0);

        Appointment::factory()->for($this->structureA)->create([
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA->id,
            'starts_at' => $startsAt,
            'duration_minutes' => 30,
            'status' => 'planifie',
        ]);

        $freeStart = $startsAt->clone()->addMinutes(30);

        $this->actingAs($this->secretaireA)->postJson('/api/appointments', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA->id,
            'starts_at' => $freeStart->toDateTimeString(),
            'duration_minutes' => 30,
            'reason' => 'Consultation de suivi',
        ])->assertCreated();
    }

    public function test_appointment_lifecycle_events_are_dispatched(): void
    {
        Event::fake([RendezVousCree::class, RendezVousModifie::class, RendezVousAnnule::class]);

        $startsAt = now()->addDay()->setTime(10, 0);

        $created = $this->actingAs($this->secretaireA)->postJson('/api/appointments', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA->id,
            'starts_at' => $startsAt->toDateTimeString(),
            'duration_minutes' => 20,
            'reason' => 'Première visite',
        ])->assertCreated()->json('data.id');

        Event::assertDispatched(RendezVousCree::class);

        $this->actingAs($this->secretaireA)->putJson("/api/appointments/{$created}", [
            'reason' => 'Motif modifié',
        ])->assertOk();

        Event::assertDispatched(RendezVousModifie::class);

        $this->actingAs($this->secretaireA)->postJson("/api/appointments/{$created}/cancel")->assertOk();

        Event::assertDispatched(RendezVousAnnule::class);
    }

    // --- Permissions --------------------------------------------------

    public function test_a_secretary_cannot_create_a_consultation(): void
    {
        $this->actingAs($this->secretaireA)->postJson('/api/consultations', [
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA->id,
            'site_id' => $this->siteA->id,
            'reason' => 'Fièvre',
        ])->assertForbidden();
    }

    public function test_a_nurse_cannot_close_a_consultation_or_code_a_diagnosis(): void
    {
        $consultation = Consultation::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA->id,
            'site_id' => $this->siteA->id,
            'status' => 'en_cours',
        ]);

        $this->actingAs($this->infirmierA)
            ->postJson("/api/consultations/{$consultation->id}/close")
            ->assertForbidden();

        $icdCode = IcdCode::factory()->create(['code' => 'J11', 'label' => 'Grippe']);

        $this->actingAs($this->infirmierA)
            ->postJson("/api/consultations/{$consultation->id}/diagnoses", [
                'icd_code_id' => $icdCode->id,
                'type' => 'principal',
            ])
            ->assertForbidden();
    }

    public function test_a_doctor_can_create_and_close_a_consultation(): void
    {
        $response = $this->actingAs($this->medecinA)->postJson('/api/consultations', [
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA->id,
            'site_id' => $this->siteA->id,
            'reason' => 'Douleur abdominale',
            'weight_kg' => 70,
            'height_cm' => 175,
        ])->assertCreated();

        $this->assertEquals(22.86, $response->json('data.vitals.bmi'));

        $consultationId = $response->json('data.id');

        $this->actingAs($this->medecinA)
            ->postJson("/api/consultations/{$consultationId}/close")
            ->assertOk()
            ->assertJsonPath('data.status', 'terminee');
    }

    // --- Moteur de codification CIM ------------------------------------

    public function test_a_coded_diagnosis_stays_unchanged_in_history_even_after_the_referentiel_is_updated(): void
    {
        $icdCode = IcdCode::factory()->create([
            'code' => 'E11',
            'version' => 'CIM-10',
            'label' => 'Diabète sucré non insulino-dépendant',
        ]);

        $consultation = Consultation::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA->id,
            'site_id' => $this->siteA->id,
        ]);

        $response = $this->actingAs($this->medecinA)
            ->postJson("/api/consultations/{$consultation->id}/diagnoses", [
                'icd_code_id' => $icdCode->id,
                'type' => 'principal',
                'status' => 'confirme',
            ])
            ->assertCreated();

        $this->assertEquals('E11', $response->json('data.code'));
        $this->assertEquals('Diabète sucré non insulino-dépendant', $response->json('data.label'));

        // The referentiel evolves after the fact (relabelling / reclassification).
        $icdCode->update(['label' => 'Diabète de type 2 (libellé révisé 2027)']);

        $diagnosis = ConsultationDiagnosis::query()->where('consultation_id', $consultation->id)->firstOrFail();

        $this->assertEquals('E11', $diagnosis->code_snapshot);
        $this->assertEquals('Diabète sucré non insulino-dépendant', $diagnosis->label_snapshot);
        $this->assertEquals('CIM-10', $diagnosis->version_snapshot);

        $this->actingAs($this->medecinA)
            ->getJson("/api/consultations/{$consultation->id}")
            ->assertOk()
            ->assertJsonPath('data.diagnoses.0.label', 'Diabète sucré non insulino-dépendant');
    }

    public function test_icd_codes_can_be_searched_and_navigated_hierarchically(): void
    {
        $chapter = IcdCode::factory()->create(['code' => 'IX', 'version' => 'CIM-10', 'level' => 'chapitre', 'label' => 'Maladies de l’appareil circulatoire']);
        IcdCode::factory()->create(['code' => 'I10', 'version' => 'CIM-10', 'level' => 'code', 'parent_id' => $chapter->id, 'label' => 'Hypertension essentielle']);

        $this->actingAs($this->medecinA)
            ->getJson('/api/icd-codes?search=hypertension')
            ->assertOk()
            ->assertJsonFragment(['code' => 'I10']);

        $this->actingAs($this->medecinA)
            ->getJson("/api/icd-codes/{$chapter->id}/children")
            ->assertOk()
            ->assertJsonFragment(['code' => 'I10']);
    }

    // --- Accueil / file d'attente ---------------------------------------

    public function test_queue_status_transitions_are_timestamped_and_average_wait_is_computed(): void
    {
        $entry = QueueEntry::factory()->for($this->structureA)->create([
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'status' => 'en_attente',
            'arrived_at' => now()->subMinutes(20),
            'called_at' => null,
        ]);

        $this->actingAs($this->secretaireA)
            ->patchJson("/api/queue-entries/{$entry->id}/status", ['status' => 'appele'])
            ->assertOk()
            ->assertJsonPath('data.status', 'appele');

        $entry->refresh();
        $this->assertNotNull($entry->called_at);

        $stats = $this->actingAs($this->secretaireA)
            ->getJson('/api/queue-entries-stats')
            ->assertOk();

        $this->assertEquals(1, $stats->json('entries_count'));
        $this->assertIsNumeric($stats->json('average_wait_minutes'));
    }

    // --- Isolation multi-tenant ------------------------------------------

    public function test_a_user_cannot_see_appointments_consultations_or_queue_entries_from_another_structure(): void
    {
        $siteB = Site::factory()->for($this->structureB)->create();
        $patientB = Patient::factory()->for($this->structureB)->create();
        $medecinB = User::factory()->for($this->structureB)->create();

        $foreignAppointment = Appointment::factory()->for($this->structureB)->create([
            'site_id' => $siteB->id,
            'patient_id' => $patientB->id,
            'practitioner_id' => $medecinB->id,
        ]);

        $foreignConsultation = Consultation::factory()->for($this->structureB)->create([
            'patient_id' => $patientB->id,
            'practitioner_id' => $medecinB->id,
            'site_id' => $siteB->id,
        ]);

        $foreignQueueEntry = QueueEntry::factory()->for($this->structureB)->create([
            'site_id' => $siteB->id,
            'patient_id' => $patientB->id,
        ]);

        $this->actingAs($this->medecinA)
            ->getJson("/api/appointments/{$foreignAppointment->id}")
            ->assertNotFound();

        $this->actingAs($this->medecinA)
            ->getJson("/api/consultations/{$foreignConsultation->id}")
            ->assertNotFound();

        $this->actingAs($this->secretaireA)
            ->getJson('/api/queue-entries')
            ->assertOk()
            ->assertJsonMissing(['id' => $foreignQueueEntry->id]);
    }

    // --- Parcours complet + timeline -------------------------------------

    public function test_the_full_care_cycle_feeds_the_patient_timeline(): void
    {
        $icdCode = IcdCode::factory()->create(['code' => 'J11', 'version' => 'CIM-10', 'label' => 'Grippe, virus non identifié']);

        $appointmentId = $this->actingAs($this->secretaireA)->postJson('/api/appointments', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA->id,
            'starts_at' => now()->addHour()->toDateTimeString(),
            'duration_minutes' => 20,
            'reason' => 'Fièvre',
        ])->assertCreated()->json('data.id');

        $queueEntryId = $this->actingAs($this->secretaireA)->postJson('/api/queue-entries', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'appointment_id' => $appointmentId,
            'service' => 'Médecine générale',
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->secretaireA)
            ->patchJson("/api/queue-entries/{$queueEntryId}/status", ['status' => 'en_consultation'])
            ->assertOk();

        $consultationId = $this->actingAs($this->medecinA)->postJson('/api/consultations', [
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA->id,
            'site_id' => $this->siteA->id,
            'appointment_id' => $appointmentId,
            'reason' => 'Fièvre et toux',
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->medecinA)->postJson("/api/consultations/{$consultationId}/diagnoses", [
            'icd_code_id' => $icdCode->id,
            'type' => 'principal',
            'status' => 'confirme',
        ])->assertCreated();

        $this->actingAs($this->medecinA)
            ->postJson("/api/consultations/{$consultationId}/close")
            ->assertOk()
            ->assertJsonPath('data.status', 'terminee');

        $timeline = $this->actingAs($this->medecinA)
            ->getJson("/api/patients/{$this->patientA->id}/timeline")
            ->assertOk();

        $entry = collect($timeline->json('data'))->firstWhere('type', 'consultation');

        $this->assertNotNull($entry, 'La consultation clôturée doit apparaître dans la timeline du patient.');
        $this->assertEquals($consultationId, $entry['data']['id']);
    }

    // --- Regression: create responses must reflect DB defaults ----------
    //
    // Appointment/QueueEntry/Consultation carry DB-level column defaults
    // (status, is_recurring) that Eloquent::create() does not populate on
    // the in-memory model it returns. A caller reading the create response
    // directly (rather than issuing a follow-up GET) would otherwise see
    // status: null instead of the real default.

    public function test_created_appointment_response_reflects_the_database_default_status(): void
    {
        $response = $this->actingAs($this->secretaireA)->postJson('/api/appointments', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA->id,
            'starts_at' => now()->addDay()->setTime(9, 0)->toDateTimeString(),
            'duration_minutes' => 20,
            'reason' => 'Consultation de suivi',
        ])->assertCreated();

        $response->assertJsonPath('data.status', 'planifie');
        $response->assertJsonPath('data.is_recurring', false);
    }

    public function test_created_queue_entry_response_reflects_the_database_default_status(): void
    {
        $response = $this->actingAs($this->secretaireA)->postJson('/api/queue-entries', [
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'service' => 'Médecine générale',
        ])->assertCreated();

        $response->assertJsonPath('data.status', 'en_attente');
    }

    public function test_created_consultation_response_reflects_the_database_default_status(): void
    {
        $response = $this->actingAs($this->medecinA)->postJson('/api/consultations', [
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA->id,
            'site_id' => $this->siteA->id,
            'reason' => 'Douleur abdominale',
        ])->assertCreated();

        $response->assertJsonPath('data.status', 'en_cours');
    }

    // --- Regression: single-day calendar view -----------------------------

    public function test_appointment_day_view_returns_appointments_starting_anytime_that_day(): void
    {
        $day = now()->addDay()->toDateString();

        Appointment::factory()->for($this->structureA)->create([
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA->id,
            'starts_at' => "{$day} 15:30:00",
            'status' => 'planifie',
        ]);

        $this->actingAs($this->secretaireA)
            ->getJson("/api/appointments?from={$day}&to={$day}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // --- Moteur CIM: mapping CIM-10 <-> CIM-11 ----------------------------

    public function test_icd_code_equivalents_can_be_looked_up_in_either_direction(): void
    {
        $cim10 = IcdCode::factory()->create(['code' => 'E11', 'version' => 'CIM-10', 'label' => 'Diabète sucré de type 2']);
        $cim11 = IcdCode::factory()->create(['code' => '5A11', 'version' => 'CIM-11', 'label' => 'Diabète sucré de type 2']);

        IcdCodeMapping::query()->create([
            'code_source' => 'E11',
            'version_source' => 'CIM-10',
            'code_cible' => '5A11',
            'version_cible' => 'CIM-11',
        ]);

        $this->actingAs($this->medecinA)
            ->getJson("/api/icd-codes/{$cim10->id}/equivalents")
            ->assertOk()
            ->assertJsonFragment(['code' => '5A11']);

        $this->actingAs($this->medecinA)
            ->getJson("/api/icd-codes/{$cim11->id}/equivalents")
            ->assertOk()
            ->assertJsonFragment(['code' => 'E11']);
    }
}
