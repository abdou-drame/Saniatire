<?php

namespace Tests\Feature;

use App\Domain\Facturation\Models\Invoice;
use App\Domain\Laboratoire\Models\LabOrder;
use App\Domain\Laboratoire\Models\LabOrderItem;
use App\Domain\Laboratoire\Models\LabSample;
use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationTemplate;
use App\Domain\Patient\Models\Patient;
use App\Domain\Prescripteur\Models\ExternalPrescriber;
use App\Domain\Shared\Tenancy\TenantScope;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Étape 7b, point 6 du prompt d'audit : vérifie que les nouveaux
 * événements métier de l'étape 7b déclenchent bien une entrée
 * `notifications` via le NotificationDispatcher générique de l'étape 7a
 * (aucune logique de notification nouvelle/dupliquée — seulement de
 * nouveaux Event::listen() dans AppServiceProvider).
 */
class Step7bNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structure;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(NotificationTemplateSeeder::class);

        $this->structure = Structure::factory()->create();
        $this->site = Site::factory()->for($this->structure)->create();
    }

    public function test_booking_an_appointment_from_the_patient_portal_dispatches_a_confirmation_notification(): void
    {
        $practitioner = User::factory()->for($this->structure)->create();
        $patient = Patient::factory()->withPortalActivated()->for($this->structure)->create(['email' => 'patient@example.com']);

        $this->actingAs($patient, 'patient')->postJson('/api/portail-patient/rendez-vous', [
            'site_id' => $this->site->id,
            'practitioner_id' => $practitioner->id,
            'starts_at' => now()->addDays(3)->setTime(10, 0)->toDateTimeString(),
            'duration_minutes' => 30,
            'reason' => 'Consultation en ligne',
        ])->assertCreated();

        $this->assertTrue(
            Notification::where('type_evenement', 'rdv_cree')
                ->where('notifiable_type', Patient::class)
                ->where('notifiable_id', $patient->id)
                ->exists()
        );
    }

    public function test_a_lab_result_transmitted_for_an_external_prescriber_dispatches_a_notification(): void
    {
        $prescriber = ExternalPrescriber::factory()->withPortalActivated()->for($this->structure)->create(['email' => 'prescripteur@example.com']);
        $patient = Patient::factory()->for($this->structure)->create();
        $admin = User::factory()->for($this->structure)->create();
        $admin->assignRole('administrateur');

        $labOrder = LabOrder::factory()->for($this->structure)->create([
            'patient_id' => $patient->id,
            'requester_type' => ExternalPrescriber::class,
            'requester_id' => $prescriber->id,
        ]);
        $labOrderItem = LabOrderItem::factory()->create(['lab_order_id' => $labOrder->id]);
        $labSample = LabSample::factory()->create(['lab_order_id' => $labOrder->id]);

        $labResult = $labSample->results()->create([
            'lab_order_item_id' => $labOrderItem->id,
            'value' => '5.2',
            'unit' => 'mg/L',
            'reference_min' => 0,
            'reference_max' => 10,
            'interpretation' => 'normal',
            'status' => 'validation_technique_attente',
        ]);

        $this->actingAs($admin)->patchJson("/api/lab-results/{$labResult->id}/validate-technique")->assertOk();
        $this->actingAs($admin)->patchJson("/api/lab-results/{$labResult->id}/validate-biologique")->assertOk();
        $this->actingAs($admin)->patchJson("/api/lab-results/{$labResult->id}/transmit")->assertOk();

        $this->assertTrue(
            Notification::where('type_evenement', 'resultat_disponible_prescripteur')
                ->where('notifiable_type', ExternalPrescriber::class)
                ->where('notifiable_id', $prescriber->id)
                ->exists()
        );
    }

    public function test_an_accepted_referral_notifies_the_origin_structures_referring_practitioner(): void
    {
        $structureDestination = Structure::factory()->create();

        $medecinOrigine = User::factory()->for($this->structure)->create(['email' => 'referent@example.com']);
        $medecinOrigine->assignRole('medecin');

        $medecinDestination = User::factory()->for($structureDestination)->create();
        $medecinDestination->assignRole('medecin');

        $patient = Patient::factory()->for($this->structure)->create();

        $referralId = $this->actingAs($medecinOrigine)
            ->postJson('/api/patient-referrals', [
                'structure_destination_id' => $structureDestination->id,
                'patient_id' => $patient->id,
                'praticien_referent_id' => $medecinOrigine->id,
                'motif' => 'Avis spécialisé',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($medecinDestination)
            ->postJson("/api/patient-referrals/{$referralId}/accept")
            ->assertOk();

        // Le médecin d'origine (destinataire de la notification) appartient à
        // une autre structure que l'acteur courant (structure destination) :
        // sans ce bypass, TenantScope sur Notification filtrerait sur la
        // structure de $medecinDestination et masquerait à tort le résultat.
        $this->assertTrue(
            Notification::withoutGlobalScope(TenantScope::class)
                ->where('type_evenement', 'referencement_accepte')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $medecinOrigine->id)
                ->exists()
        );
    }

    public function test_a_refused_referral_notifies_the_origin_structures_referring_practitioner(): void
    {
        $structureDestination = Structure::factory()->create();

        $medecinOrigine = User::factory()->for($this->structure)->create(['email' => 'referent@example.com']);
        $medecinOrigine->assignRole('medecin');

        $medecinDestination = User::factory()->for($structureDestination)->create();
        $medecinDestination->assignRole('medecin');

        $patient = Patient::factory()->for($this->structure)->create();

        $referralId = $this->actingAs($medecinOrigine)
            ->postJson('/api/patient-referrals', [
                'structure_destination_id' => $structureDestination->id,
                'patient_id' => $patient->id,
                'praticien_referent_id' => $medecinOrigine->id,
                'motif' => 'Avis spécialisé',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($medecinDestination)
            ->postJson("/api/patient-referrals/{$referralId}/refuse")
            ->assertOk();

        $this->assertTrue(
            Notification::withoutGlobalScope(TenantScope::class)
                ->where('type_evenement', 'referencement_refuse')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $medecinOrigine->id)
                ->exists()
        );
    }
}
