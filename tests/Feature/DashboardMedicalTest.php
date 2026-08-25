<?php

namespace Tests\Feature;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Consultation\Models\ConsultationDiagnosis;
use App\Domain\Facturation\Models\BillableItem;
use App\Domain\Hospitalisation\Models\Bed;
use App\Domain\Hospitalisation\Models\Ward;
use App\Domain\Icd\Models\IcdCode;
use App\Domain\Patient\Models\Patient;
use App\Domain\Queue\Models\QueueEntry;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardMedicalTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Site $siteA;

    private Site $siteA2;

    private Patient $patientA;

    private User $medecinA1;

    private User $medecinA2;

    private User $directeurMedicalA;

    private User $caissierA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structureA = Structure::factory()->create();
        $this->siteA = Site::factory()->for($this->structureA)->create();
        $this->siteA2 = Site::factory()->for($this->structureA)->create();
        $this->patientA = Patient::factory()->for($this->structureA)->create();

        $this->medecinA1 = User::factory()->for($this->structureA)->create();
        $this->medecinA1->assignRole('medecin');

        $this->medecinA2 = User::factory()->for($this->structureA)->create();
        $this->medecinA2->assignRole('medecin');

        $this->directeurMedicalA = User::factory()->for($this->structureA)->create();
        $this->directeurMedicalA->assignRole('directeur_medical');

        $this->caissierA = User::factory()->for($this->structureA)->create();
        $this->caissierA->assignRole('caissier');
    }

    // --- Consultations : total + répartition praticien/spécialité --------

    public function test_consultations_summary_counts_total_and_breaks_down_by_practitioner_and_specialty(): void
    {
        $from = '2026-08-01';
        $to = '2026-08-31';
        $inPeriod = '2026-08-15 10:00:00';
        $outOfPeriod = '2026-06-01 10:00:00';

        Consultation::factory()->for($this->structureA)->count(3)->create([
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA1->id,
            'site_id' => $this->siteA->id,
            'specialty_type' => 'generale',
            'created_at' => $inPeriod,
        ]);

        Consultation::factory()->for($this->structureA)->count(2)->create([
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA2->id,
            'site_id' => $this->siteA->id,
            'specialty_type' => 'cardiologie',
            'created_at' => $inPeriod,
        ]);

        // Hors période : ne doit pas être compté.
        Consultation::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA1->id,
            'site_id' => $this->siteA->id,
            'specialty_type' => 'generale',
            'created_at' => $outOfPeriod,
        ]);

        $response = $this->actingAs($this->medecinA1)
            ->getJson("/api/dashboards/medical?from={$from}&to={$to}")
            ->assertOk();

        $this->assertEquals(5, $response->json('consultations.total'));

        $parPraticien = collect($response->json('consultations.par_praticien'))->keyBy('practitioner_id');
        $this->assertEquals(3, $parPraticien[$this->medecinA1->id]['total']);
        $this->assertEquals(2, $parPraticien[$this->medecinA2->id]['total']);

        $parSpecialite = collect($response->json('consultations.par_specialite'))->keyBy('specialty_type');
        $this->assertEquals(3, $parSpecialite['generale']['total']);
        $this->assertEquals(2, $parSpecialite['cardiologie']['total']);
    }

    // --- Occupation des lits et temps d'attente : réutilisation exacte ---

    public function test_bed_occupancy_and_queue_wait_time_match_the_reused_endpoints(): void
    {
        $ward = Ward::factory()->for($this->structureA)->create(['site_id' => $this->siteA->id]);
        Bed::factory()->for($this->structureA)->count(4)->create(['site_id' => $this->siteA->id, 'ward_id' => $ward->id, 'status' => 'libre']);
        Bed::factory()->for($this->structureA)->count(1)->create(['site_id' => $this->siteA->id, 'ward_id' => $ward->id, 'status' => 'occupe']);

        // 1 lit occupé sur 5 -> 20%.
        QueueEntry::factory()->for($this->structureA)->create([
            'site_id' => $this->siteA->id,
            'patient_id' => $this->patientA->id,
            'arrived_at' => now()->subMinutes(30),
            'called_at' => now()->subMinutes(10),
        ]);

        // from/to explicites : QueueEntryController::stats() retombe sur
        // "aujourd'hui seulement" quand la requête n'en porte pas, ce qui
        // rend le test flaky si l'exécution tombe dans les 30 premières
        // minutes après minuit (arrived_at bascule alors sur la veille et
        // sort de la fenêtre par défaut). Une fenêtre explicite large
        // élimine cette dépendance à l'horloge murale.
        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();

        $response = $this->actingAs($this->medecinA1)
            ->getJson("/api/dashboards/medical?from={$from}&to={$to}")
            ->assertOk();

        $wardRow = collect($response->json('occupation_lits'))->firstWhere('ward_id', $ward->id);
        $this->assertEquals(5, $wardRow['total_beds']);
        $this->assertEquals(1, $wardRow['occupied_beds']);
        $this->assertEquals(20.0, $wardRow['occupancy_rate']);

        $this->assertEquals(1, $response->json('temps_attente.entries_count'));
        $this->assertEquals(20.0, $response->json('temps_attente.average_wait_minutes'));
    }

    // --- Actes par spécialité (reconstruit depuis billable_items) --------

    public function test_actes_par_specialite_sums_billable_items_by_categorie(): void
    {
        $from = '2026-08-01';
        $to = '2026-08-31';

        BillableItem::factory()->for($this->structureA)->count(2)->create([
            'patient_id' => $this->patientA->id,
            'categorie' => 'consultation',
            'montant_total' => 5000,
            'statut' => 'a_facturer',
            'created_at' => '2026-08-10 09:00:00',
        ]);

        BillableItem::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'categorie' => 'laboratoire',
            'montant_total' => 8000,
            'statut' => 'a_facturer',
            'created_at' => '2026-08-10 09:00:00',
        ]);

        // Annulé : exclu du total.
        BillableItem::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'categorie' => 'consultation',
            'montant_total' => 5000,
            'statut' => 'annulee',
            'created_at' => '2026-08-10 09:00:00',
        ]);

        $response = $this->actingAs($this->medecinA1)
            ->getJson("/api/dashboards/medical?from={$from}&to={$to}")
            ->assertOk();

        $this->assertFalse($response->json('actes_par_specialite.site_filtre_applique'));

        $rows = collect($response->json('actes_par_specialite.data'))->keyBy('categorie');
        $this->assertEquals(2, $rows['consultation']['total_actes']);
        $this->assertEquals(10000, $rows['consultation']['montant_total']);
        $this->assertEquals(1, $rows['laboratoire']['total_actes']);
        $this->assertEquals(8000, $rows['laboratoire']['montant_total']);
    }

    // --- Épidémiologie : enrichissement site/sexe/âge ---------------------

    public function test_epidemiological_stats_counts_by_code_and_filters_by_site(): void
    {
        $chapter = IcdCode::factory()->create(['code' => 'IX', 'level' => 'chapitre', 'label' => 'Appareil circulatoire']);
        $codeI10 = IcdCode::factory()->create(['code' => 'I10', 'level' => 'code', 'parent_id' => $chapter->id]);
        $codeE11 = IcdCode::factory()->create(['code' => 'E11', 'level' => 'code']);

        $consult1 = Consultation::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA1->id,
            'site_id' => $this->siteA->id,
        ]);
        $consult2 = Consultation::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA1->id,
            'site_id' => $this->siteA->id,
        ]);
        // Sur l'autre site : doit être exclu par le filtre site_id=siteA.
        $consult3 = Consultation::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA1->id,
            'site_id' => $this->siteA2->id,
        ]);
        $consultOtherSite = Consultation::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA1->id,
            'site_id' => $this->siteA2->id,
        ]);

        ConsultationDiagnosis::factory()->create(['consultation_id' => $consult1->id, 'icd_code_id' => $codeI10->id, 'code_snapshot' => 'I10', 'label_snapshot' => 'Hypertension']);
        ConsultationDiagnosis::factory()->create(['consultation_id' => $consult2->id, 'icd_code_id' => $codeI10->id, 'code_snapshot' => 'I10', 'label_snapshot' => 'Hypertension']);
        ConsultationDiagnosis::factory()->create(['consultation_id' => $consult3->id, 'icd_code_id' => $codeE11->id, 'code_snapshot' => 'E11', 'label_snapshot' => 'Diabète']);
        ConsultationDiagnosis::factory()->create(['consultation_id' => $consultOtherSite->id, 'icd_code_id' => $codeI10->id, 'code_snapshot' => 'I10', 'label_snapshot' => 'Hypertension']);

        // Sans filtre : I10 x3, E11 x1.
        $response = $this->actingAs($this->directeurMedicalA)
            ->getJson('/api/icd-codes/stats')
            ->assertOk();

        $stats = collect($response->json('stats'))->keyBy('code');
        $this->assertEquals(3, $stats['I10']['total']);
        $this->assertEquals(1, $stats['E11']['total']);

        // Avec filtre site_id=siteA : I10 x2 (exclut consultOtherSite), E11 absent (consult3 est sur l'autre site).
        $filtered = $this->actingAs($this->directeurMedicalA)
            ->getJson("/api/icd-codes/stats?site_id={$this->siteA->id}")
            ->assertOk();

        $filteredStats = collect($filtered->json('stats'))->keyBy('code');
        $this->assertEquals(2, $filteredStats['I10']['total']);
        $this->assertFalse(isset($filteredStats['E11']));
    }

    public function test_epidemiological_stats_filters_by_sex_and_age(): void
    {
        $code = IcdCode::factory()->create(['code' => 'I10', 'level' => 'code']);

        $femalePatient = Patient::factory()->for($this->structureA)->create(['sex' => 'F', 'birth_date' => now()->subYears(40)->toDateString()]);
        $malePatient = Patient::factory()->for($this->structureA)->create(['sex' => 'M', 'birth_date' => now()->subYears(10)->toDateString()]);

        $consultFemale = Consultation::factory()->for($this->structureA)->create([
            'patient_id' => $femalePatient->id,
            'practitioner_id' => $this->medecinA1->id,
            'site_id' => $this->siteA->id,
        ]);
        $consultMale = Consultation::factory()->for($this->structureA)->create([
            'patient_id' => $malePatient->id,
            'practitioner_id' => $this->medecinA1->id,
            'site_id' => $this->siteA->id,
        ]);

        ConsultationDiagnosis::factory()->create(['consultation_id' => $consultFemale->id, 'icd_code_id' => $code->id, 'code_snapshot' => 'I10']);
        ConsultationDiagnosis::factory()->create(['consultation_id' => $consultMale->id, 'icd_code_id' => $code->id, 'code_snapshot' => 'I10']);

        // sex=F : seule la patiente de 40 ans doit être comptée.
        $bySex = $this->actingAs($this->directeurMedicalA)
            ->getJson('/api/icd-codes/stats?sex=F')
            ->assertOk();
        $this->assertEquals(1, collect($bySex->json('stats'))->firstWhere('code', 'I10')['total']);

        // age_min=18 : exclut le patient de 10 ans.
        $byAge = $this->actingAs($this->directeurMedicalA)
            ->getJson('/api/icd-codes/stats?age_min=18')
            ->assertOk();
        $this->assertEquals(1, collect($byAge->json('stats'))->firstWhere('code', 'I10')['total']);
    }

    // --- Cache : le résultat mis en cache ne change qu'après invalidation -

    public function test_epidemiological_stats_are_cached_until_the_cache_key_is_forgotten(): void
    {
        $code = IcdCode::factory()->create(['code' => 'I10', 'level' => 'code']);
        $consult = Consultation::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA1->id,
            'site_id' => $this->siteA->id,
        ]);
        ConsultationDiagnosis::factory()->create(['consultation_id' => $consult->id, 'icd_code_id' => $code->id, 'code_snapshot' => 'I10', 'label_snapshot' => 'Hypertension']);

        $from = now()->subMonth()->startOfDay()->toDateString();
        $to = now()->endOfDay()->toDateString();

        $first = $this->actingAs($this->directeurMedicalA)->getJson('/api/icd-codes/stats')->assertOk();
        $this->assertEquals(1, collect($first->json('stats'))->firstWhere('code', 'I10')['total']);

        // Nouvelle donnée après le premier appel : le cache doit encore
        // renvoyer l'ancien total tant qu'il n'est pas invalidé.
        $consult2 = Consultation::factory()->for($this->structureA)->create([
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->medecinA1->id,
            'site_id' => $this->siteA->id,
        ]);
        ConsultationDiagnosis::factory()->create(['consultation_id' => $consult2->id, 'icd_code_id' => $code->id, 'code_snapshot' => 'I10', 'label_snapshot' => 'Hypertension']);

        $stillCached = $this->actingAs($this->directeurMedicalA)->getJson('/api/icd-codes/stats')->assertOk();
        $this->assertEquals(1, collect($stillCached->json('stats'))->firstWhere('code', 'I10')['total']);

        $cacheKey = sprintf(
            'icd_stats.%s.%s.%s.%s.%s.%s.%s.%s',
            $this->structureA->id,
            $from,
            $to,
            'code',
            'all',
            'all',
            'none',
            'none',
        );
        Cache::forget($cacheKey);

        $afterInvalidation = $this->actingAs($this->directeurMedicalA)->getJson('/api/icd-codes/stats')->assertOk();
        $this->assertEquals(2, collect($afterInvalidation->json('stats'))->firstWhere('code', 'I10')['total']);
    }

    // --- Permissions --------------------------------------------------

    public function test_a_cashier_cannot_access_the_medical_dashboard(): void
    {
        $this->actingAs($this->caissierA)
            ->getJson('/api/dashboards/medical')
            ->assertForbidden();
    }
}
