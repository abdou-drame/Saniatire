<?php

namespace Tests\Feature;

use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Étape 7b §5/§7 : PatientReferral est le seul endroit du projet où un
 * partage de données entre deux structure_id est intentionnel. Ces tests
 * vérifient que ReferralVisibilityScope reste strictement borné aux deux
 * structures parties au référencement (jamais une troisième), que les
 * actions réservées à la destination le sont réellement, et que chaque
 * franchissement de frontière par la structure non-origine est tracé dans
 * activity_log sous le log_name 'partage_inter_structure'.
 */
class PatientReferralTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureOrigine;

    private Structure $structureDestination;

    private Structure $structureTierce;

    private User $medecinOrigine;

    private User $medecinDestination;

    private User $medecinTiers;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->structureOrigine = Structure::factory()->create();
        $this->structureDestination = Structure::factory()->create();
        $this->structureTierce = Structure::factory()->create();

        $this->medecinOrigine = User::factory()->for($this->structureOrigine)->create();
        $this->medecinOrigine->assignRole('medecin');

        $this->medecinDestination = User::factory()->for($this->structureDestination)->create();
        $this->medecinDestination->assignRole('medecin');

        $this->medecinTiers = User::factory()->for($this->structureTierce)->create();
        $this->medecinTiers->assignRole('medecin');

        $this->patient = Patient::factory()->for($this->structureOrigine)->create();
    }

    private function creerReferencement(): int
    {
        return $this->actingAs($this->medecinOrigine)
            ->postJson('/api/patient-referrals', [
                'structure_destination_id' => $this->structureDestination->id,
                'patient_id' => $this->patient->id,
                'praticien_referent_id' => $this->medecinOrigine->id,
                'motif' => 'Avis spécialisé cardiologie',
            ])
            ->assertCreated()
            ->json('data.id');
    }

    public function test_a_third_structure_cannot_see_a_referral_between_two_other_structures(): void
    {
        $referralId = $this->creerReferencement();

        $this->actingAs($this->medecinTiers)
            ->getJson("/api/patient-referrals/{$referralId}")
            ->assertNotFound();
    }

    public function test_both_the_origin_and_destination_structures_can_see_the_referral(): void
    {
        $referralId = $this->creerReferencement();

        $this->actingAs($this->medecinOrigine)
            ->getJson("/api/patient-referrals/{$referralId}")
            ->assertOk();

        $this->actingAs($this->medecinDestination)
            ->getJson("/api/patient-referrals/{$referralId}")
            ->assertOk();
    }

    public function test_the_destination_structure_viewing_the_referral_is_traced_as_inter_structure_access(): void
    {
        $referralId = $this->creerReferencement();

        $this->actingAs($this->medecinDestination)
            ->getJson("/api/patient-referrals/{$referralId}")
            ->assertOk();

        $activity = Activity::query()
            ->where('log_name', 'partage_inter_structure')
            ->where('subject_id', $referralId)
            ->first();

        $this->assertNotNull($activity, 'Expected a partage_inter_structure audit entry for the destination structure access.');
        $this->assertSame($this->medecinDestination->id, $activity->causer_id);
        $this->assertSame('consultation', $activity->properties['action']);
        $this->assertSame($this->structureDestination->id, $activity->properties['structure_agissante_id']);
        $this->assertSame($this->structureOrigine->id, $activity->properties['structure_origine_id']);
        $this->assertSame($this->structureDestination->id, $activity->properties['structure_destination_id']);
        $this->assertSame($this->patient->id, $activity->properties['patient_id']);
    }

    public function test_the_origin_structure_viewing_its_own_referral_is_not_traced_as_inter_structure_access(): void
    {
        $referralId = $this->creerReferencement();

        $this->actingAs($this->medecinOrigine)
            ->getJson("/api/patient-referrals/{$referralId}")
            ->assertOk();

        $activity = Activity::query()
            ->where('log_name', 'partage_inter_structure')
            ->where('subject_id', $referralId)
            ->first();

        $this->assertNull($activity, 'The origin structure accessing its own referral should not produce an inter-structure audit entry.');
    }

    public function test_the_origin_structure_cannot_accept_its_own_referral(): void
    {
        $referralId = $this->creerReferencement();

        $this->actingAs($this->medecinOrigine)
            ->postJson("/api/patient-referrals/{$referralId}/accept")
            ->assertForbidden();
    }

    public function test_a_third_structure_cannot_reach_the_referral_to_accept_it(): void
    {
        $referralId = $this->creerReferencement();

        $this->actingAs($this->medecinTiers)
            ->postJson("/api/patient-referrals/{$referralId}/accept")
            ->assertNotFound();
    }

    public function test_the_destination_structure_can_accept_the_referral(): void
    {
        $referralId = $this->creerReferencement();

        $this->actingAs($this->medecinDestination)
            ->postJson("/api/patient-referrals/{$referralId}/accept")
            ->assertOk()
            ->assertJsonPath('data.statut', 'accepte');
    }

    public function test_the_destination_structure_can_refuse_the_referral(): void
    {
        $referralId = $this->creerReferencement();

        $this->actingAs($this->medecinDestination)
            ->postJson("/api/patient-referrals/{$referralId}/refuse")
            ->assertOk()
            ->assertJsonPath('data.statut', 'refuse');
    }

    public function test_the_destination_structure_can_complete_the_referral_with_a_report(): void
    {
        $referralId = $this->creerReferencement();

        $this->actingAs($this->medecinDestination)
            ->postJson("/api/patient-referrals/{$referralId}/accept")
            ->assertOk();

        $this->actingAs($this->medecinDestination)
            ->postJson("/api/patient-referrals/{$referralId}/complete", [
                'compte_rendu_retour' => 'Patient vu, traitement ajusté, retour au médecin traitant.',
            ])
            ->assertOk()
            ->assertJsonPath('data.statut', 'complete')
            ->assertJsonPath('data.compte_rendu_retour', 'Patient vu, traitement ajusté, retour au médecin traitant.');
    }

    public function test_patient_resume_exposes_only_the_minimal_summary_to_the_destination_structure(): void
    {
        $referralId = $this->creerReferencement();

        $response = $this->actingAs($this->medecinDestination)
            ->getJson("/api/patient-referrals/{$referralId}/patient-resume")
            ->assertOk();

        $response->assertJson([
            'nom' => trim($this->patient->first_name.' '.$this->patient->last_name),
            'numero_patient' => $this->patient->patient_number,
        ]);

        $activity = Activity::query()
            ->where('log_name', 'partage_inter_structure')
            ->where('subject_id', $referralId)
            ->first();

        $this->assertNotNull($activity, 'Expected a partage_inter_structure audit entry when the destination structure reads the patient resume.');
    }

    public function test_a_third_structure_cannot_reach_the_patient_resume(): void
    {
        $referralId = $this->creerReferencement();

        $this->actingAs($this->medecinTiers)
            ->getJson("/api/patient-referrals/{$referralId}/patient-resume")
            ->assertNotFound();
    }
}
