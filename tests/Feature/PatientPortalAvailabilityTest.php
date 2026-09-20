<?php

namespace Tests\Feature;

use App\Domain\Appointment\Models\Appointment;
use App\Domain\Patient\Models\Patient;
use App\Domain\Rh\Models\WorkSchedule;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Verrouille le principe central du portail patient : les créneaux affichés
 * et acceptés à la création ne sont jamais inventés côté client, ils
 * reflètent toujours PractitionerPresenceService (horaires RH réels,
 * gardes/astreintes, congés validés) et Appointment::hasConflict(), sans
 * aucune dérogation possible pour le guard patient (contrairement au staff).
 */
class PatientPortalAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structure;

    private Patient $patient;

    private User $practitioner;

    private Site $site;

    /** Le lundi le plus proche dans le futur, pour des dates de test déterministes. */
    private Carbon $monday;

    protected function setUp(): void
    {
        parent::setUp();

        $this->structure = Structure::factory()->create();
        $this->patient = Patient::factory()->withPortalActivated()->for($this->structure)->create();
        $this->practitioner = User::factory()->for($this->structure)->create();
        $this->site = Site::factory()->for($this->structure)->create();

        $this->monday = Carbon::parse('next monday')->startOfDay();

        // Planning réel et restreint : uniquement le lundi 08:00-12:00.
        WorkSchedule::factory()->create([
            'structure_id' => $this->structure->id,
            'user_id' => $this->practitioner->id,
            'site_id' => $this->site->id,
            'jour_semaine' => 1,
            'date' => null,
            'heure_debut' => '08:00:00',
            'heure_fin' => '12:00:00',
            'type' => 'normal',
        ]);
    }

    public function test_a_patient_cannot_book_a_slot_outside_the_practitioners_real_schedule(): void
    {
        $tuesday10h = $this->monday->clone()->addDay()->setTime(10, 0);

        $response = $this->actingAs($this->patient, 'patient')
            ->postJson('/api/portail-patient/rendez-vous', [
                'site_id' => $this->site->id,
                'practitioner_id' => $this->practitioner->id,
                'starts_at' => $tuesday10h->toIso8601String(),
                'duration_minutes' => 30,
                'reason' => 'Consultation hors planning',
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('indisponible', $response->json('message'));
        $this->assertSame(0, Appointment::count());
    }

    public function test_a_patient_cannot_book_a_slot_overlapping_an_existing_appointment(): void
    {
        $existingStart = $this->monday->clone()->setTime(9, 0);

        Appointment::factory()->for($this->structure)->create([
            'site_id' => $this->site->id,
            'patient_id' => Patient::factory()->for($this->structure)->create()->id,
            'practitioner_id' => $this->practitioner->id,
            'starts_at' => $existingStart,
            'duration_minutes' => 30,
            'status' => 'confirme',
        ]);

        $overlapStart = $this->monday->clone()->setTime(9, 15);

        $response = $this->actingAs($this->patient, 'patient')
            ->postJson('/api/portail-patient/rendez-vous', [
                'site_id' => $this->site->id,
                'practitioner_id' => $this->practitioner->id,
                'starts_at' => $overlapStart->toIso8601String(),
                'duration_minutes' => 30,
                'reason' => 'Consultation en chevauchement',
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('déjà occupé', $response->json('message'));
        $this->assertSame(1, Appointment::count());
    }

    public function test_a_patient_can_book_a_slot_that_genuinely_matches_the_practitioners_schedule(): void
    {
        $validStart = $this->monday->clone()->setTime(9, 0);

        $response = $this->actingAs($this->patient, 'patient')
            ->postJson('/api/portail-patient/rendez-vous', [
                'site_id' => $this->site->id,
                'practitioner_id' => $this->practitioner->id,
                'starts_at' => $validStart->toIso8601String(),
                'duration_minutes' => 30,
                'reason' => 'Consultation de suivi',
            ]);

        $response->assertCreated();
        $this->assertSame(1, Appointment::count());
    }

    public function test_a_garde_day_never_appears_as_a_bookable_slot_for_a_patient(): void
    {
        // Reproduit le bug réel : un praticien avec 3 jours "normal" et un
        // 4e jour de type "garde" ne doit montrer/accepter que les 3 jours
        // normaux — la garde est de la couverture d'urgence, jamais un
        // créneau de consultation ordinaire.
        $thursday = $this->monday->clone()->addDays(3);

        WorkSchedule::factory()->create([
            'structure_id' => $this->structure->id,
            'user_id' => $this->practitioner->id,
            'site_id' => $this->site->id,
            'jour_semaine' => $thursday->dayOfWeek,
            'date' => null,
            'heure_debut' => '20:00:00',
            'heure_fin' => '23:00:00',
            'type' => 'garde',
        ]);

        $creneauxResponse = $this->actingAs($this->patient, 'patient')
            ->getJson('/api/portail-patient/creneaux-disponibles?'.http_build_query([
                'practitioner_id' => $this->practitioner->id,
                'from' => $this->monday->toDateString(),
                'to' => $thursday->toDateString(),
                'duration_minutes' => 30,
            ]))
            ->assertOk();

        $creneaux = collect($creneauxResponse->json('creneaux'))->map(fn (string $iso) => Carbon::parse($iso));
        $this->assertNotEmpty($creneaux);
        foreach ($creneaux as $slot) {
            $this->assertSame(1, $slot->dayOfWeek, "Un créneau de garde ({$slot}) est proposé au patient alors que seul le lundi est un jour normal.");
        }

        $bookAttempt = $this->actingAs($this->patient, 'patient')
            ->postJson('/api/portail-patient/rendez-vous', [
                'site_id' => $this->site->id,
                'practitioner_id' => $this->practitioner->id,
                'starts_at' => $thursday->clone()->setTime(21, 0)->toIso8601String(),
                'duration_minutes' => 30,
                'reason' => 'Tentative de RDV pendant une garde',
            ]);

        $bookAttempt->assertStatus(422);
        $this->assertSame(0, Appointment::count());
    }

    public function test_the_displayed_slots_exactly_match_the_practitioners_real_schedule(): void
    {
        $from = $this->monday->clone();
        $to = $this->monday->clone()->addDays(6);

        $response = $this->actingAs($this->patient, 'patient')
            ->getJson('/api/portail-patient/creneaux-disponibles?'.http_build_query([
                'practitioner_id' => $this->practitioner->id,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'duration_minutes' => 30,
            ]));

        $response->assertOk();
        $creneaux = collect($response->json('creneaux'))->map(fn (string $iso) => Carbon::parse($iso));

        // Uniquement des créneaux de 30 min entre 08:00 et 11:30 (dernier
        // départ possible avant la borne de 12:00), et uniquement le lundi.
        $this->assertNotEmpty($creneaux);
        foreach ($creneaux as $slot) {
            $this->assertSame(1, $slot->dayOfWeek, "Un créneau {$slot} est proposé un jour hors planning (seul le lundi est configuré).");
            $this->assertTrue(
                $slot->format('H:i') >= '08:00' && $slot->format('H:i') <= '11:30',
                "Un créneau {$slot} est proposé hors de la plage horaire réelle 08:00-12:00 du praticien."
            );
        }

        // Exactement les 8 créneaux de 30 min attendus entre 08:00 et 12:00.
        $this->assertCount(8, $creneaux);

        // Un créneau déjà pris par un autre patient ne doit plus apparaître.
        Appointment::factory()->for($this->structure)->create([
            'site_id' => $this->site->id,
            'patient_id' => Patient::factory()->for($this->structure)->create()->id,
            'practitioner_id' => $this->practitioner->id,
            'starts_at' => $this->monday->clone()->setTime(8, 0),
            'duration_minutes' => 30,
            'status' => 'confirme',
        ]);

        $afterBooking = $this->actingAs($this->patient, 'patient')
            ->getJson('/api/portail-patient/creneaux-disponibles?'.http_build_query([
                'practitioner_id' => $this->practitioner->id,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'duration_minutes' => 30,
            ]))
            ->assertOk();

        $remaining = collect($afterBooking->json('creneaux'));
        $this->assertFalse($remaining->contains($this->monday->clone()->setTime(8, 0)->toIso8601String()));
        $this->assertCount(7, $remaining);
    }
}
