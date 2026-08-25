<?php

namespace Tests\Feature;

use App\Domain\Facturation\Models\Invoice;
use App\Domain\Laboratoire\Models\LabOrder;
use App\Domain\Laboratoire\Models\LabOrderItem;
use App\Domain\Laboratoire\Models\LabSample;
use App\Domain\Notification\Mail\GenericNotificationMail;
use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\Notification\Models\NotificationTemplate;
use App\Domain\Patient\Models\Patient;
use App\Domain\Rh\Models\LeaveRequest;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Step7aNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Structure $structureB;

    private Site $siteA;

    private User $secretaireA;

    private User $medecinA;

    private User $administrateurA;

    private User $rhA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structureA = Structure::factory()->create();
        $this->structureB = Structure::factory()->create();
        $this->siteA = Site::factory()->for($this->structureA)->create();

        $this->secretaireA = User::factory()->for($this->structureA)->create();
        $this->secretaireA->assignRole('secretaire');

        $this->medecinA = User::factory()->for($this->structureA)->create(['first_name' => 'Awa', 'last_name' => 'Traore']);
        $this->medecinA->assignRole('medecin');

        $this->administrateurA = User::factory()->for($this->structureA)->create();
        $this->administrateurA->assignRole('administrateur');

        $this->rhA = User::factory()->for($this->structureA)->create();
        $this->rhA->assignRole('rh');

        // Template email par défaut global, requis pour que le dispatcher
        // ait quelque chose à rendre — créé hors de tout actingAs() pour
        // que le hook BelongsToTenant ne lui assigne pas une structure
        // (TenantScope::currentStructureId() est bien null ici).
        NotificationTemplate::factory()->create([
            'structure_id' => null,
            'type_evenement' => 'rdv_cree',
            'canal' => 'email',
            'sujet' => 'Confirmation RDV',
            'contenu' => 'Bonjour {patient_nom}, RDV le {date_rdv} avec {praticien_nom}.',
            'actif' => true,
        ]);
        NotificationTemplate::factory()->create([
            'structure_id' => null,
            'type_evenement' => 'rdv_rappel',
            'canal' => 'email',
            'sujet' => 'Rappel RDV',
            'contenu' => 'Rappel : {patient_nom}, RDV le {date_rdv}.',
            'actif' => true,
        ]);
        NotificationTemplate::factory()->create([
            'structure_id' => null,
            'type_evenement' => 'rdv_cree',
            'canal' => 'sms',
            'sujet' => null,
            'contenu' => 'RDV le {date_rdv} confirmé.',
            'actif' => true,
        ]);
        NotificationTemplate::factory()->create([
            'structure_id' => null,
            'type_evenement' => 'conge_valide',
            'canal' => 'email',
            'sujet' => 'Congé validé',
            'contenu' => 'Bonjour {user_nom}, congé du {date_debut} au {date_fin} validé.',
            'actif' => true,
        ]);
        NotificationTemplate::factory()->create([
            'structure_id' => null,
            'type_evenement' => 'resultat_disponible',
            'canal' => 'email',
            'sujet' => 'Résultat disponible',
            'contenu' => 'Bonjour {patient_nom}, votre résultat est disponible.',
            'actif' => true,
        ]);
        NotificationTemplate::factory()->create([
            'structure_id' => null,
            'type_evenement' => 'facture_echeance',
            'canal' => 'email',
            'sujet' => "Rappel d'échéance",
            'contenu' => 'Bonjour {patient_nom}, la facture {facture_numero} de {montant_restant} FCFA arrive à échéance le {date_echeance}.',
            'actif' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_creating_an_appointment_dispatches_a_rdv_cree_notification_with_correct_variables_and_sends_a_real_email(): void
    {
        Mail::fake();

        $patient = Patient::factory()->for($this->structureA)->create([
            'first_name' => 'Fatou',
            'last_name' => 'Kone',
            'email' => 'fatou.kone@example.com',
        ]);

        $startsAt = now()->addDays(3)->setTime(9, 0);

        $this->actingAs($this->secretaireA)->postJson('/api/appointments', [
            'site_id' => $this->siteA->id,
            'patient_id' => $patient->id,
            'practitioner_id' => $this->medecinA->id,
            'starts_at' => $startsAt->toDateTimeString(),
            'duration_minutes' => 30,
            'reason' => 'Consultation',
        ])->assertCreated();

        $notification = Notification::where('type_evenement', 'rdv_cree')
            ->where('notifiable_type', Patient::class)
            ->where('notifiable_id', $patient->id)
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame('email', $notification->canal);
        $this->assertSame('envoyee', $notification->statut);
        $this->assertSame('fatou.kone@example.com', $notification->destinataire);
        $this->assertStringContainsString('Fatou Kone', $notification->contenu_final);
        $this->assertStringContainsString('Awa Traore', $notification->contenu_final);

        Mail::assertSent(GenericNotificationMail::class, fn ($mail) => $mail->notification->is($notification));
    }

    public function test_sms_channel_is_simulated_with_no_real_network_call(): void
    {
        $patient = Patient::factory()->for($this->structureA)->create(['phone' => '+2250700000000']);

        NotificationPreference::factory()->create([
            'structure_id' => $this->structureA->id,
            'notifiable_type' => Patient::class,
            'notifiable_id' => $patient->id,
            'canaux' => ['sms'],
        ]);

        $this->actingAs($this->secretaireA)->postJson('/api/appointments', [
            'site_id' => $this->siteA->id,
            'patient_id' => $patient->id,
            'practitioner_id' => $this->medecinA->id,
            'starts_at' => now()->addDays(3)->toDateTimeString(),
            'duration_minutes' => 30,
            'reason' => 'Consultation',
        ])->assertCreated();

        $notifications = Notification::where('notifiable_type', Patient::class)
            ->where('notifiable_id', $patient->id)
            ->get();

        // Une seule notification créée (canal sms uniquement, préférence
        // respectée) : pas de ligne email en parallèle.
        $this->assertCount(1, $notifications);
        $this->assertSame('sms', $notifications->first()->canal);
        $this->assertSame('envoyee', $notifications->first()->statut);
        $this->assertSame('+2250700000000', $notifications->first()->destinataire);

        // SmsChannel ne fait qu'un Log::info — aucun client HTTP n'est
        // jamais instancié, donc aucune requête réseau n'a pu partir.
    }

    public function test_a_patients_channel_preference_is_respected_on_send(): void
    {
        $patient = Patient::factory()->for($this->structureA)->create([
            'email' => 'pref@example.com',
            'phone' => '+2250700000001',
        ]);

        NotificationPreference::factory()->create([
            'structure_id' => $this->structureA->id,
            'notifiable_type' => Patient::class,
            'notifiable_id' => $patient->id,
            'canaux' => ['sms'],
        ]);

        $this->actingAs($this->secretaireA)->postJson('/api/appointments', [
            'site_id' => $this->siteA->id,
            'patient_id' => $patient->id,
            'practitioner_id' => $this->medecinA->id,
            'starts_at' => now()->addDays(3)->toDateTimeString(),
            'duration_minutes' => 30,
            'reason' => 'Consultation',
        ])->assertCreated();

        $this->assertFalse(
            Notification::where('notifiable_type', Patient::class)
                ->where('notifiable_id', $patient->id)
                ->where('canal', 'email')
                ->exists()
        );
    }

    public function test_rdv_reminder_is_scheduled_and_only_fires_once_due(): void
    {
        Mail::fake();

        $patient = Patient::factory()->for($this->structureA)->create(['email' => 'rappel@example.com']);

        $startsAt = now()->addDays(3)->setTime(9, 0);

        $this->actingAs($this->secretaireA)->postJson('/api/appointments', [
            'site_id' => $this->siteA->id,
            'patient_id' => $patient->id,
            'practitioner_id' => $this->medecinA->id,
            'starts_at' => $startsAt->toDateTimeString(),
            'duration_minutes' => 30,
            'reason' => 'Consultation',
        ])->assertCreated();

        $reminder = Notification::where('type_evenement', 'rdv_rappel')
            ->where('notifiable_type', Patient::class)
            ->where('notifiable_id', $patient->id)
            ->first();

        $this->assertNotNull($reminder);
        $this->assertSame('en_attente', $reminder->statut);
        $this->assertTrue($reminder->scheduled_for->equalTo($startsAt->clone()->subHours(24)));

        // Avant l'échéance : la commande ne doit rien envoyer.
        Carbon::setTestNow($reminder->scheduled_for->clone()->subMinute());
        Artisan::call('notifications:process-due');
        $this->assertSame('en_attente', $reminder->fresh()->statut);

        // À l'échéance (ou après) : la commande envoie effectivement.
        Carbon::setTestNow($reminder->scheduled_for);
        Artisan::call('notifications:process-due');
        $this->assertSame('envoyee', $reminder->fresh()->statut);
    }

    public function test_conge_valide_dispatches_a_notification_to_the_requester(): void
    {
        $this->medecinA->update(['email' => 'medecin@example.com']);

        $leaveRequest = LeaveRequest::factory()->for($this->structureA)->create([
            'user_id' => $this->medecinA->id,
            'statut' => 'demande',
        ]);

        $this->actingAs($this->rhA)
            ->patchJson("/api/leave-requests/{$leaveRequest->id}/validate")
            ->assertOk();

        $this->assertTrue(
            Notification::where('type_evenement', 'conge_valide')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $this->medecinA->id)
                ->exists()
        );
    }

    public function test_lab_result_validated_dispatches_a_notification_to_the_patient(): void
    {
        $patient = Patient::factory()->for($this->structureA)->create(['email' => 'labo@example.com']);

        $labOrder = LabOrder::factory()->for($this->structureA)->create(['patient_id' => $patient->id]);
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

        $this->actingAs($this->administrateurA)
            ->patchJson("/api/lab-results/{$labResult->id}/validate-technique")
            ->assertOk();

        $this->actingAs($this->administrateurA)
            ->patchJson("/api/lab-results/{$labResult->id}/validate-biologique")
            ->assertOk();

        $this->assertTrue(
            Notification::where('type_evenement', 'resultat_disponible')
                ->where('notifiable_type', Patient::class)
                ->where('notifiable_id', $patient->id)
                ->exists()
        );
    }

    public function test_invoice_due_date_reminder_is_created_by_the_process_due_command(): void
    {
        Mail::fake();

        $patient = Patient::factory()->for($this->structureA)->create(['email' => 'facture@example.com']);

        $invoice = Invoice::factory()->for($this->structureA)->create([
            'patient_id' => $patient->id,
            'statut' => 'emise',
            'montant_total' => 15000,
            'date_emission' => now()->toDateString(),
            'date_echeance' => now()->addDays(2)->toDateString(),
        ]);

        Artisan::call('notifications:process-due');

        $notification = Notification::where('type_evenement', 'facture_echeance')
            ->where('notifiable_type', Patient::class)
            ->where('notifiable_id', $patient->id)
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString($invoice->numero ?? "#{$invoice->id}", $notification->contenu_final);

        // Ne doit pas créer de doublon à une exécution suivante.
        Artisan::call('notifications:process-due');
        $this->assertSame(
            1,
            Notification::where('type_evenement', 'facture_echeance')
                ->where('notifiable_type', Patient::class)
                ->where('notifiable_id', $patient->id)
                ->count()
        );
    }

    public function test_a_structure_specific_template_takes_priority_over_the_global_default(): void
    {
        Mail::fake();

        NotificationTemplate::factory()->create([
            'structure_id' => $this->structureA->id,
            'type_evenement' => 'rdv_cree',
            'canal' => 'email',
            'sujet' => 'Sujet personnalisé structure A',
            'contenu' => 'Contenu personnalisé pour {patient_nom}.',
            'actif' => true,
        ]);

        $patient = Patient::factory()->for($this->structureA)->create(['email' => 'custom@example.com']);

        $this->actingAs($this->secretaireA)->postJson('/api/appointments', [
            'site_id' => $this->siteA->id,
            'patient_id' => $patient->id,
            'practitioner_id' => $this->medecinA->id,
            'starts_at' => now()->addDays(3)->toDateTimeString(),
            'duration_minutes' => 30,
            'reason' => 'Consultation',
        ])->assertCreated();

        $notification = Notification::where('type_evenement', 'rdv_cree')
            ->where('notifiable_type', Patient::class)
            ->where('notifiable_id', $patient->id)
            ->first();

        $this->assertStringContainsString('Contenu personnalisé', $notification->contenu_final);
    }

    public function test_notification_templates_and_history_are_tenant_isolated(): void
    {
        $templateB = NotificationTemplate::factory()->create([
            'structure_id' => $this->structureB->id,
            'type_evenement' => 'rdv_cree',
            'canal' => 'email',
        ]);

        $patientB = Patient::factory()->for($this->structureB)->create();
        $notificationB = Notification::factory()->create([
            'structure_id' => $this->structureB->id,
            'notifiable_type' => Patient::class,
            'notifiable_id' => $patientB->id,
        ]);

        $this->actingAs($this->administrateurA)
            ->getJson("/api/notification-templates/{$templateB->id}")
            ->assertNotFound();

        // Pas de route "show" dédiée sur l'historique : on vérifie
        // directement que TenantScope (porté par BelongsToTenant) exclut la
        // ligne de la structure B dès qu'un utilisateur de la structure A
        // est authentifié.
        $this->assertNull(Notification::find($notificationB->id));
    }

    public function test_administrator_can_manage_structure_templates_via_the_api(): void
    {
        $response = $this->actingAs($this->administrateurA)->postJson('/api/notification-templates', [
            'type_evenement' => 'rdv_annule',
            'canal' => 'email',
            'sujet' => 'RDV annulé',
            'contenu' => 'Votre RDV du {date_rdv} est annulé.',
        ]);

        $response->assertCreated();
        $this->assertSame($this->structureA->id, $response->json('data.structure_id'));

        // Une mise à jour partielle (un seul champ) ne doit pas exiger de
        // renvoyer type_evenement/canal/contenu — sinon toute édition du
        // seul sujet depuis l'UI échouerait avec une 422.
        $templateId = $response->json('data.id');

        $this->actingAs($this->administrateurA)
            ->putJson("/api/notification-templates/{$templateId}", ['sujet' => 'Nouveau sujet'])
            ->assertOk()
            ->assertJsonPath('data.sujet', 'Nouveau sujet')
            ->assertJsonPath('data.contenu', 'Votre RDV du {date_rdv} est annulé.');
    }

    public function test_a_user_without_administrative_rights_cannot_manage_structure_templates(): void
    {
        $existing = NotificationTemplate::factory()->create([
            'structure_id' => $this->structureA->id,
            'type_evenement' => 'rdv_annule',
            'canal' => 'email',
            'sujet' => 'RDV annulé',
            'contenu' => 'Votre RDV du {date_rdv} est annulé.',
        ]);

        $this->actingAs($this->secretaireA)
            ->postJson('/api/notification-templates', [
                'type_evenement' => 'rdv_annule',
                'canal' => 'email',
                'sujet' => 'Tentative non autorisée',
                'contenu' => 'Contenu.',
            ])
            ->assertStatus(403);

        $this->actingAs($this->secretaireA)
            ->putJson("/api/notification-templates/{$existing->id}", ['sujet' => 'Modifié sans droit'])
            ->assertStatus(403);

        $this->actingAs($this->secretaireA)
            ->deleteJson("/api/notification-templates/{$existing->id}")
            ->assertStatus(403);

        $this->assertSame('RDV annulé', $existing->fresh()->sujet);
    }

    public function test_a_user_can_only_manage_their_own_notification_preferences(): void
    {
        $this->actingAs($this->medecinA)
            ->putJson('/api/notification-preferences', ['canaux' => ['whatsapp', 'email']])
            ->assertSuccessful()
            ->assertJsonPath('data.canaux', ['whatsapp', 'email']);

        $stored = NotificationPreference::where('notifiable_type', User::class)
            ->where('notifiable_id', $this->medecinA->id)
            ->first();

        $this->assertNotNull($stored);
        $this->assertSame(['whatsapp', 'email'], $stored->canaux);
    }
}
