<?php

namespace Tests\Feature;

use App\Domain\Caisse\Models\Payment;
use App\Domain\Consultation\Models\Consultation;
use App\Domain\Consultation\Models\ConsultationDiagnosis;
use App\Domain\Facturation\Models\Invoice;
use App\Domain\Hospitalisation\Models\Bed;
use App\Domain\Hospitalisation\Models\Ward;
use App\Domain\Icd\Models\IcdCode;
use App\Domain\Notification\Models\Notification;
use App\Domain\Patient\Models\Patient;
use App\Domain\Qualite\Models\Complaint;
use App\Domain\Qualite\Models\PatientSatisfactionSurvey;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Audit de vérification demandé explicitement par l'utilisateur après
 * l'étape 8 : chaque indicateur est recalculé à la main puis comparé au
 * résultat réel de l'API. Chaque section imprime la fixture, le calcul
 * attendu et le résultat obtenu sur STDOUT (exécuter avec le binaire
 * phpunit directement, sans --no-output, pour voir la sortie en direct).
 * Fichier temporaire d'audit — pas destiné à rester dans la suite
 * permanente (la couverture de régression vit déjà dans
 * Dashboard*Test.php / ComplaintTest.php / etc.).
 */
class Step8AuditVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Site $siteA;

    private Patient $patientA;

    private User $medecinA;

    private User $directionA;

    private User $directeurMedicalA;

    private User $infirmierA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structureA = Structure::factory()->create();
        $this->siteA = Site::factory()->for($this->structureA)->create();
        $this->patientA = Patient::factory()->for($this->structureA)->create(['email' => 'patienta@example.test']);

        $this->medecinA = User::factory()->for($this->structureA)->create();
        $this->medecinA->assignRole('medecin');

        $this->directionA = User::factory()->for($this->structureA)->create();
        $this->directionA->assignRole('direction');

        // La stats CIM brute (/api/icd-codes/stats) exige la permission
        // icd.export, que le rôle direction n'a délibérément pas (il y
        // accède uniquement via le tableau de bord médical, cf.
        // RolePermissionSeeder). directeur_medical, lui, l'a.
        $this->directeurMedicalA = User::factory()->for($this->structureA)->create();
        $this->directeurMedicalA->assignRole('directeur_medical');

        $this->infirmierA = User::factory()->for($this->structureA)->create();
        $this->infirmierA->assignRole('infirmier');
    }

    private function log(string $line): void
    {
        fwrite(STDOUT, $line."\n");
    }

    // §1a --------------------------------------------------------------

    public function test_audit_1a_nombre_de_consultations(): void
    {
        $this->log("\n=== §1a Nombre de consultations sur une période ===");

        Consultation::factory()->for($this->structureA)->count(6)->create([
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA->id,
            'site_id' => $this->siteA->id,
            'created_at' => '2031-01-15 10:00:00',
        ]);
        Consultation::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA->id,
            'site_id' => $this->siteA->id,
            'created_at' => '2030-12-01 10:00:00',
        ]);
        Consultation::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA->id,
            'site_id' => $this->siteA->id,
            'created_at' => '2031-03-01 10:00:00',
        ]);
        $this->log('Fixture : 6 consultations dans la période 2031-01-01..2031-01-31, +2 hors période.');
        $this->log('Attendu (calcul manuel) : consultations.total = 6');

        $response = $this->actingAs($this->medecinA)
            ->getJson('/api/dashboards/medical?from=2031-01-01&to=2031-01-31')
            ->assertOk();

        $actual = $response->json('consultations.total');
        $this->log("Obtenu (API GET /api/dashboards/medical) : consultations.total = {$actual}");

        $this->assertEquals(6, $actual);
    }

    // §1b --------------------------------------------------------------

    public function test_audit_1b_statistiques_epidemiologiques(): void
    {
        $this->log("\n=== §1b Statistiques épidémiologiques par code CIM ===");

        $i10 = IcdCode::factory()->create(['code' => 'I10', 'level' => 'code']);
        $j45 = IcdCode::factory()->create(['code' => 'J45', 'level' => 'code']);
        $e11 = IcdCode::factory()->create(['code' => 'E11', 'level' => 'code']);

        foreach ([['I10', $i10, 4], ['J45', $j45, 2], ['E11', $e11, 1]] as [$codeStr, $icdCode, $n]) {
            for ($i = 0; $i < $n; $i++) {
                $consult = Consultation::factory()->for($this->structureA)->create([
                    'patient_id' => $this->patientA->id,
                    'practitioner_id' => $this->medecinA->id,
                    'site_id' => $this->siteA->id,
                    'created_at' => '2031-02-10 09:00:00',
                ]);
                ConsultationDiagnosis::factory()->create([
                    'consultation_id' => $consult->id,
                    'icd_code_id' => $icdCode->id,
                    'code_snapshot' => $codeStr,
                    'label_snapshot' => "Libellé {$codeStr}",
                    'created_at' => '2031-02-10 09:00:00',
                ]);
            }
        }
        $this->log('Fixture : diagnostics créés en 2031-02 — I10 x4, J45 x2, E11 x1.');
        $this->log('Attendu (calcul manuel) : I10=4, J45=2, E11=1');

        $response = $this->actingAs($this->directeurMedicalA)
            ->getJson('/api/icd-codes/stats?from=2031-02-01&to=2031-02-28')
            ->assertOk();

        $stats = collect($response->json('stats'))->keyBy('code');
        $this->log('Obtenu (API GET /api/icd-codes/stats) : I10='.$stats['I10']['total'].', J45='.$stats['J45']['total'].', E11='.$stats['E11']['total']);

        $this->assertEquals(4, $stats['I10']['total']);
        $this->assertEquals(2, $stats['J45']['total']);
        $this->assertEquals(1, $stats['E11']['total']);
    }

    // §1c --------------------------------------------------------------

    public function test_audit_1c_taux_occupation_lits(): void
    {
        $this->log("\n=== §1c Taux d'occupation des lits ===");

        $ward = Ward::factory()->for($this->structureA)->create(['site_id' => $this->siteA->id]);
        Bed::factory()->for($this->structureA)->count(5)->create(['site_id' => $this->siteA->id, 'ward_id' => $ward->id, 'status' => 'libre']);
        Bed::factory()->for($this->structureA)->count(3)->create(['site_id' => $this->siteA->id, 'ward_id' => $ward->id, 'status' => 'occupe']);

        $this->log('Fixture : 8 lits au total (5 libres + 3 occupés).');
        $this->log('Attendu (calcul manuel) : 3 / 8 = 37.5 %');

        $response = $this->actingAs($this->medecinA)
            ->getJson('/api/dashboards/medical')
            ->assertOk();

        $wardRow = collect($response->json('occupation_lits'))->firstWhere('ward_id', $ward->id);
        $this->log('Obtenu (API GET /api/dashboards/medical, occupation_lits) : '.$wardRow['occupied_beds'].'/'.$wardRow['total_beds'].' = '.$wardRow['occupancy_rate'].' %');

        $this->assertEquals(8, $wardRow['total_beds']);
        $this->assertEquals(3, $wardRow['occupied_beds']);
        $this->assertEquals(37.5, $wardRow['occupancy_rate']);
    }

    // §1d --------------------------------------------------------------

    public function test_audit_1d_chiffre_affaires_par_periode(): void
    {
        $this->log("\n=== §1d Chiffre d'affaires (encaissements) par période ===");

        $invoice = Invoice::factory()->for($this->structureA)->create(['site_id' => $this->siteA->id, 'patient_id' => $this->patientA->id]);

        Payment::factory()->for($this->structureA)->create(['invoice_id' => $invoice->id, 'site_id' => $this->siteA->id, 'montant' => 12000, 'paid_at' => '2031-04-05']);
        Payment::factory()->for($this->structureA)->create(['invoice_id' => $invoice->id, 'site_id' => $this->siteA->id, 'montant' => 8000, 'paid_at' => '2031-04-10']);
        Payment::factory()->for($this->structureA)->create(['invoice_id' => $invoice->id, 'site_id' => $this->siteA->id, 'montant' => 5000, 'paid_at' => '2031-04-20']);
        // Hors période : exclu.
        Payment::factory()->for($this->structureA)->create(['invoice_id' => $invoice->id, 'site_id' => $this->siteA->id, 'montant' => 99999, 'paid_at' => '2031-05-01']);
        // Mobile money en échec : exclu (pas un encaissement réel).
        Payment::factory()->for($this->structureA)->create(['invoice_id' => $invoice->id, 'site_id' => $this->siteA->id, 'montant' => 7777, 'paid_at' => '2031-04-12', 'mode_paiement' => 'mobile_money', 'statut_mobile_money' => 'failed']);

        $this->log('Fixture : paiements 12000 + 8000 + 5000 en avril 2031 ; +99999 hors période ; +7777 mobile money échoué.');
        $this->log('Attendu (calcul manuel) : 12000 + 8000 + 5000 = 25000');

        $response = $this->actingAs($this->directionA)
            ->getJson('/api/dashboards/financier?from=2031-04-01&to=2031-04-30')
            ->assertOk();

        $actual = $response->json('encaissements_total');
        $this->log("Obtenu (API GET /api/dashboards/financier) : encaissements_total = {$actual}");

        $this->assertEquals(25000, $actual);
    }

    // §1e --------------------------------------------------------------

    public function test_audit_1e_taux_de_recouvrement(): void
    {
        $this->log("\n=== §1e Taux de recouvrement ===");

        $invoice = Invoice::factory()->for($this->structureA)->create([
            'site_id' => $this->siteA->id, 'patient_id' => $this->patientA->id,
            'date_emission' => '2031-05-10', 'montant_total' => 20000, 'statut' => 'emise',
        ]);

        Payment::factory()->for($this->structureA)->create(['invoice_id' => $invoice->id, 'site_id' => $this->siteA->id, 'montant' => 9000, 'paid_at' => '2031-05-12']);
        Payment::factory()->for($this->structureA)->create(['invoice_id' => $invoice->id, 'site_id' => $this->siteA->id, 'montant' => 6000, 'paid_at' => '2031-05-15']);

        $this->log('Fixture : 1 facture de 20000 émise en mai 2031, réglée partiellement par 9000 + 6000 = 15000.');
        $this->log('Attendu (calcul manuel) : 15000 / 20000 = 0.75');

        $response = $this->actingAs($this->directionA)
            ->getJson('/api/dashboards/financier?from=2031-05-01&to=2031-05-31')
            ->assertOk();

        $actual = $response->json('taux_recouvrement');
        $this->log("Obtenu (API GET /api/dashboards/financier) : taux_recouvrement = {$actual}");

        $this->assertEquals(0.75, $actual);
    }

    // §1f --------------------------------------------------------------

    public function test_audit_1f_score_satisfaction_moyen(): void
    {
        $this->log("\n=== §1f Score de satisfaction moyen ===");

        foreach ([7, 8, 9, 6, 10] as $note) {
            PatientSatisfactionSurvey::factory()->for($this->structureA)->create([
                'patient_id' => $this->patientA->id, 'note' => $note, 'date' => '2031-06-15',
            ]);
        }
        $this->log('Fixture : 5 évaluations avec notes 7, 8, 9, 6, 10 en juin 2031.');
        $this->log('Attendu (calcul manuel) : (7+8+9+6+10) / 5 = 40 / 5 = 8.0');

        $response = $this->actingAs($this->directionA)
            ->getJson('/api/dashboards/qualite?from=2031-06-01&to=2031-06-30')
            ->assertOk();

        $actual = $response->json('score_moyen_satisfaction');
        $this->log("Obtenu (API GET /api/dashboards/qualite) : score_moyen_satisfaction = {$actual}");

        $this->assertEquals(8.0, $actual);
    }

    // §2 -----------------------------------------------------------------

    public function test_audit_2_tableau_direction_isolation_et_consolidation(): void
    {
        $this->log("\n=== §2 Tableau Direction/Groupe : isolation + consolidation ===");

        $siteA2 = Site::factory()->for($this->structureA)->create(['name' => 'Site A2']);
        $invoiceA1 = Invoice::factory()->for($this->structureA)->create(['site_id' => $this->siteA->id, 'patient_id' => $this->patientA->id]);
        $invoiceA2 = Invoice::factory()->for($this->structureA)->create(['site_id' => $siteA2->id, 'patient_id' => $this->patientA->id]);

        Payment::factory()->for($this->structureA)->create(['invoice_id' => $invoiceA1->id, 'site_id' => $this->siteA->id, 'montant' => 20000, 'paid_at' => '2031-07-05']);
        Payment::factory()->for($this->structureA)->create(['invoice_id' => $invoiceA1->id, 'site_id' => $this->siteA->id, 'montant' => 10000, 'paid_at' => '2031-07-06']);
        Payment::factory()->for($this->structureA)->create(['invoice_id' => $invoiceA2->id, 'site_id' => $siteA2->id, 'montant' => 7000, 'paid_at' => '2031-07-07']);
        Payment::factory()->for($this->structureA)->create(['invoice_id' => $invoiceA2->id, 'site_id' => $siteA2->id, 'montant' => 5000, 'paid_at' => '2031-07-08']);

        $structureB = Structure::factory()->create();
        $siteB1 = Site::factory()->for($structureB)->create(['name' => 'Site B1']);
        $patientB = Patient::factory()->for($structureB)->create();
        $directionB = User::factory()->for($structureB)->create();
        $directionB->assignRole('direction');
        $invoiceB1 = Invoice::factory()->for($structureB)->create(['site_id' => $siteB1->id, 'patient_id' => $patientB->id]);
        Payment::factory()->for($structureB)->create(['invoice_id' => $invoiceB1->id, 'site_id' => $siteB1->id, 'montant' => 99999, 'paid_at' => '2031-07-05']);

        $this->log('Fixture : Structure A -> Site A1 (20000+10000=30000), Site A2 (7000+5000=12000). Structure B -> Site B1 (99999), utilisateur distinct.');
        $this->log('Attendu (calcul manuel) : Structure A ca_total = 30000 + 12000 = 42000, Site A1 rang 1, Site A2 rang 2. Structure B ne doit jamais apparaître.');

        $responseA = $this->actingAs($this->directionA)
            ->getJson('/api/dashboards/direction?from=2031-07-01&to=2031-07-31')
            ->assertOk();

        $caTotalA = $responseA->json('consolide.ca_total');
        $sitesA = collect($responseA->json('comparaison_sites'))->keyBy('site_id');
        $this->log("Obtenu (Structure A, API GET /api/dashboards/direction) : ca_total = {$caTotalA}");
        $this->log('  Site A1 : ca='.$sitesA[$this->siteA->id]['ca'].', rang='.$sitesA[$this->siteA->id]['rang']);
        $this->log('  Site A2 : ca='.$sitesA[$siteA2->id]['ca'].', rang='.$sitesA[$siteA2->id]['rang']);
        $this->log('  Site B1 présent dans la réponse de A ? '.($sitesA->has($siteB1->id) ? 'OUI (FUITE)' : 'non'));

        $this->assertEquals(42000, $caTotalA);
        $this->assertEquals(30000, $sitesA[$this->siteA->id]['ca']);
        $this->assertEquals(1, $sitesA[$this->siteA->id]['rang']);
        $this->assertEquals(12000, $sitesA[$siteA2->id]['ca']);
        $this->assertEquals(2, $sitesA[$siteA2->id]['rang']);
        $this->assertFalse($sitesA->has($siteB1->id));

        $responseB = $this->actingAs($directionB)
            ->getJson('/api/dashboards/direction?from=2031-07-01&to=2031-07-31')
            ->assertOk();

        $caTotalB = $responseB->json('consolide.ca_total');
        $sitesB = collect($responseB->json('comparaison_sites'))->keyBy('site_id');
        $this->log("Obtenu (Structure B, API GET /api/dashboards/direction) : ca_total = {$caTotalB}, nb sites visibles = ".$sitesB->count());

        $this->assertEquals(99999, $caTotalB);
        $this->assertCount(1, $sitesB);
        $this->assertFalse($sitesB->has($this->siteA->id));
        $this->assertFalse($sitesB->has($siteA2->id));
    }

    // §3 -----------------------------------------------------------------

    public function test_audit_3a_note_satisfaction_hors_plage_rejetee(): void
    {
        $this->log("\n=== §3a Rejet d'une note de satisfaction hors plage (1-10) ===");

        $response = $this->actingAs($this->directionA)->postJson('/api/patient-satisfaction-surveys', [
            'patient_id' => $this->patientA->id, 'service' => 'consultation', 'note' => 15,
        ]);

        $this->log('Fixture : note=15 envoyée (hors plage 1-10).');
        $this->log('Attendu : HTTP 422, aucune ligne créée.');
        $this->log('Obtenu : HTTP '.$response->getStatusCode().', lignes en base = '.PatientSatisfactionSurvey::count());

        $response->assertStatus(422);
        $this->assertDatabaseCount('patient_satisfaction_surveys', 0);
    }

    public function test_audit_3b_declenchement_notification_enquete(): void
    {
        $this->log("\n=== §3b Déclenchement d'une notification d'enquête de satisfaction ===");

        $this->seed(NotificationTemplateSeeder::class);

        $this->actingAs($this->directionA)->postJson('/api/patient-satisfaction-surveys/send-invitation', [
            'patient_id' => $this->patientA->id, 'service' => 'laboratoire',
        ])->assertOk();

        $exists = Notification::where('type_evenement', 'enquete_satisfaction')
            ->where('notifiable_type', Patient::class)
            ->where('notifiable_id', $this->patientA->id)
            ->exists();

        $this->log('Fixture : POST /api/patient-satisfaction-surveys/send-invitation pour patientA.');
        $this->log('Attendu : une notification type_evenement=enquete_satisfaction existe pour ce patient.');
        $this->log('Obtenu : notification trouvée = '.($exists ? 'oui' : 'non'));

        $this->assertTrue($exists);
    }

    public function test_audit_3c_workflow_reclamation_complet_avec_tracabilite(): void
    {
        $this->log("\n=== §3c Workflow réclamation complet avec traçabilité horodatée ===");

        $gestionnaireA = User::factory()->for($this->structureA)->create();
        $gestionnaireA->assignRole('secretaire');

        $created = $this->actingAs($gestionnaireA)->postJson('/api/complaints', [
            'patient_id' => $this->patientA->id, 'motif' => 'attente',
            'description' => 'Temps d\'attente trop long.', 'service_concerne' => 'consultation',
        ])->assertCreated();
        $complaintId = $created->json('data.id');
        $this->log("Étape 1 (ouverture) : statut={$created->json('data.statut')}");
        $this->assertSame('ouverte', $created->json('data.statut'));

        $assigned = $this->actingAs($this->directionA)->postJson("/api/complaints/{$complaintId}/assign", [
            'gestionnaire_id' => $gestionnaireA->id,
        ])->assertOk();
        $this->log("Étape 2 (assignation) : statut={$assigned->json('data.statut')}, gestionnaire_id={$assigned->json('data.gestionnaire_id')}");
        $this->assertSame('en_cours', $assigned->json('data.statut'));
        $this->assertSame($gestionnaireA->id, $assigned->json('data.gestionnaire_id'));

        $responded = $this->actingAs($gestionnaireA)->postJson("/api/complaints/{$complaintId}/respond", [
            'message' => 'Nous étudions votre réclamation.',
        ])->assertCreated();
        $this->log("Étape 3 (réponse) : message enregistré, auteur_id={$responded->json('data.auteur_id')}, horodatage={$responded->json('data.created_at')}");
        $this->assertSame($gestionnaireA->id, $responded->json('data.auteur_id'));

        $resolved = $this->actingAs($gestionnaireA)->postJson("/api/complaints/{$complaintId}/resolve")->assertOk();
        $this->log("Étape 4 (résolution) : statut={$resolved->json('data.statut')}, resolved_at={$resolved->json('data.resolved_at')}");
        $this->assertSame('resolue', $resolved->json('data.statut'));
        $this->assertNotNull($resolved->json('data.resolved_at'));

        $closed = $this->actingAs($gestionnaireA)->postJson("/api/complaints/{$complaintId}/close")->assertOk();
        $this->log("Étape 5 (clôture) : statut={$closed->json('data.statut')}, closed_at={$closed->json('data.closed_at')}");
        $this->assertSame('close', $closed->json('data.statut'));
        $this->assertNotNull($closed->json('data.closed_at'));

        $complaint = Complaint::find($complaintId);
        $this->log('Vérification ordre chronologique : created_at='.$complaint->created_at.' < resolved_at='.$complaint->resolved_at.' < closed_at='.$complaint->closed_at);
        $this->assertTrue($complaint->created_at->lessThanOrEqualTo($complaint->resolved_at));
        $this->assertTrue($complaint->resolved_at->lessThanOrEqualTo($complaint->closed_at));
    }

    public function test_audit_3d_delai_moyen_resolution_reclamations(): void
    {
        $this->log("\n=== §3d Délai moyen de résolution des réclamations ===");

        Complaint::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id, 'statut' => 'resolue',
            'created_at' => '2031-08-01 00:00:00', 'resolved_at' => '2031-08-03 00:00:00',
        ]);
        Complaint::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id, 'statut' => 'resolue',
            'created_at' => '2031-08-02 00:00:00', 'resolved_at' => '2031-08-04 12:00:00',
        ]);
        Complaint::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id, 'statut' => 'resolue',
            'created_at' => '2031-08-05 00:00:00', 'resolved_at' => '2031-08-05 06:00:00',
        ]);

        $this->log('Fixture : 3 réclamations résolues avec délais 48h, 60h, 6h.');
        $this->log('Attendu (calcul manuel) : (48 + 60 + 6) / 3 = 114 / 3 = 38.0 h');

        $response = $this->actingAs($this->directionA)
            ->getJson('/api/dashboards/qualite?from=2031-08-01&to=2031-08-31')
            ->assertOk();

        $actual = $response->json('delai_moyen_resolution_heures');
        $this->log("Obtenu (API GET /api/dashboards/qualite) : delai_moyen_resolution_heures = {$actual}");

        $this->assertEquals(38.0, $actual);
    }

    // §4 -----------------------------------------------------------------

    public function test_audit_4_export_csv_fidele_aux_donnees(): void
    {
        $this->log("\n=== §4 Export CSV — fidélité au jeu de données ===");

        $i10 = IcdCode::factory()->create(['code' => 'I10', 'level' => 'code']);
        $j45 = IcdCode::factory()->create(['code' => 'J45', 'level' => 'code']);

        foreach ([[$i10, 'I10', 3], [$j45, 'J45', 1]] as [$icdCode, $codeStr, $n]) {
            for ($i = 0; $i < $n; $i++) {
                $consult = Consultation::factory()->for($this->structureA)->create([
                    'patient_id' => $this->patientA->id, 'practitioner_id' => $this->medecinA->id,
                    'site_id' => $this->siteA->id, 'created_at' => '2031-09-10 09:00:00',
                ]);
                ConsultationDiagnosis::factory()->create([
                    'consultation_id' => $consult->id, 'icd_code_id' => $icdCode->id,
                    'code_snapshot' => $codeStr, 'label_snapshot' => "Libellé {$codeStr}",
                    'created_at' => '2031-09-10 09:00:00',
                ]);
            }
        }
        $this->log('Fixture : diagnostics en 2031-09 — I10 x3, J45 x1.');
        $this->log('Attendu (calcul manuel) : ligne CSV I10 total=3, ligne CSV J45 total=1.');

        $response = $this->actingAs($this->directionA)
            ->get('/api/reports/epidemiologie/export?from=2031-09-01&to=2031-09-30')
            ->assertOk();

        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", trim($response->streamedContent()))));
        $rows = array_map('str_getcsv', $lines);
        $header = array_shift($rows);
        $byCode = collect($rows)->mapWithKeys(fn ($r) => [$r[0] => $r[2]]);

        $this->log('Obtenu (contenu réel du CSV téléchargé) : header='.implode(',', $header).' | I10='.$byCode['I10'].' | J45='.$byCode['J45']);

        $this->assertSame(['code', 'label', 'total'], $header);
        $this->assertEquals('3', $byCode['I10']);
        $this->assertEquals('1', $byCode['J45']);
    }

    // §5 -----------------------------------------------------------------

    public function test_audit_5_cache_coherence_puis_expiration(): void
    {
        $this->log("\n=== §5 Cache — cohérence pendant la durée de vie puis mise à jour après expiration ===");

        $code = IcdCode::factory()->create(['code' => 'I10', 'level' => 'code']);
        $consult1 = Consultation::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id, 'practitioner_id' => $this->medecinA->id,
            'site_id' => $this->siteA->id, 'created_at' => '2031-10-10 09:00:00',
        ]);
        ConsultationDiagnosis::factory()->create([
            'consultation_id' => $consult1->id, 'icd_code_id' => $code->id,
            'code_snapshot' => 'I10', 'label_snapshot' => 'Hypertension', 'created_at' => '2031-10-10 09:00:00',
        ]);

        $first = $this->actingAs($this->directeurMedicalA)
            ->getJson('/api/icd-codes/stats?from=2031-10-01&to=2031-10-31')
            ->assertOk();
        $firstTotal = collect($first->json('stats'))->firstWhere('code', 'I10')['total'];
        $this->log("Appel 1 (1er diagnostic seul en base) : total I10 = {$firstTotal}");
        $this->assertEquals(1, $firstTotal);

        $consult2 = Consultation::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id, 'practitioner_id' => $this->medecinA->id,
            'site_id' => $this->siteA->id, 'created_at' => '2031-10-11 09:00:00',
        ]);
        ConsultationDiagnosis::factory()->create([
            'consultation_id' => $consult2->id, 'icd_code_id' => $code->id,
            'code_snapshot' => 'I10', 'label_snapshot' => 'Hypertension', 'created_at' => '2031-10-11 09:00:00',
        ]);
        $this->log('Un 2e diagnostic I10 est ajouté en base SANS invalider le cache.');

        $stillCached = $this->actingAs($this->directeurMedicalA)
            ->getJson('/api/icd-codes/stats?from=2031-10-01&to=2031-10-31')
            ->assertOk();
        $stillCachedTotal = collect($stillCached->json('stats'))->firstWhere('code', 'I10')['total'];
        $this->log("Appel 2 (pendant la durée de vie du cache) : total I10 = {$stillCachedTotal} (doit rester 1, valeur mise en cache)");
        $this->assertEquals(1, $stillCachedTotal);

        $structureId = $this->directeurMedicalA->structure_id;
        $cacheKey = sprintf('icd_stats.%s.%s.%s.%s.%s.%s.%s.%s', $structureId, '2031-10-01', '2031-10-31', 'code', 'all', 'all', 'none', 'none');
        Cache::forget($cacheKey);
        $this->log("Invalidation manuelle de la clé de cache : {$cacheKey}");

        $afterInvalidation = $this->actingAs($this->directeurMedicalA)
            ->getJson('/api/icd-codes/stats?from=2031-10-01&to=2031-10-31')
            ->assertOk();
        $afterTotal = collect($afterInvalidation->json('stats'))->firstWhere('code', 'I10')['total'];
        $this->log("Appel 3 (après invalidation) : total I10 = {$afterTotal} (doit refléter les 2 diagnostics)");

        $this->assertEquals(2, $afterTotal);
    }

    // §6 -----------------------------------------------------------------

    public function test_audit_6_permissions_par_tableau_de_bord(): void
    {
        $this->log("\n=== §6 Permissions par tableau de bord ===");

        $financier = $this->actingAs($this->infirmierA)->getJson('/api/dashboards/financier');
        $this->log('Infirmier -> GET /api/dashboards/financier : HTTP '.$financier->getStatusCode().' (attendu 403)');
        $financier->assertForbidden();

        $direction = $this->actingAs($this->medecinA)->getJson('/api/dashboards/direction');
        $this->log('Médecin (sans rôle direction) -> GET /api/dashboards/direction : HTTP '.$direction->getStatusCode().' (attendu 403)');
        $direction->assertForbidden();
    }

    // §7 -----------------------------------------------------------------

    public function test_audit_7_isolation_multi_tenant_qualite(): void
    {
        $this->log("\n=== §7 Isolation multi-tenant — patient_satisfaction_surveys / complaints ===");

        $structureB = Structure::factory()->create();
        $survey = PatientSatisfactionSurvey::factory()->for($this->structureA)->create(['patient_id' => $this->patientA->id]);
        $foreignSurvey = PatientSatisfactionSurvey::factory()->for($structureB)->create();

        $surveys = $this->actingAs($this->directionA)->getJson('/api/patient-satisfaction-surveys')->assertOk();
        $ids = collect($surveys->json('data'))->pluck('id');
        $this->log('Enquêtes visibles par Structure A : '.$ids->implode(',').' — enquête de Structure B ('.$foreignSurvey->id.') présente ? '.($ids->contains($foreignSurvey->id) ? 'OUI (FUITE)' : 'non'));
        $this->assertTrue($ids->contains($survey->id));
        $this->assertFalse($ids->contains($foreignSurvey->id));

        $foreignComplaint = Complaint::factory()->for($structureB)->create();
        $response = $this->actingAs($this->directionA)->getJson("/api/complaints/{$foreignComplaint->id}");
        $this->log('Accès à une réclamation de Structure B depuis Structure A : HTTP '.$response->getStatusCode().' (attendu 404)');
        $response->assertNotFound();
    }
}
