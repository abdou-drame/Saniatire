<?php

namespace Tests\Feature;

use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Régression pour le rapport "Dossier — historique inaccessible, même au
 * médecin" (patients_medical.view / PatientController::timeline) : les
 * appels API réels effectués pendant l'investigation n'ont PAS reproduit de
 * 403 pour medecin, sur la patiente signalée ni sur un patient plus ancien
 * — RolePermissionSeeder accorde bien patients_medical.view à medecin. Ces
 * tests couvrent malgré tout les deux angles concrets qui auraient pu
 * causer un tel symptôme, pour qu'un futur régression soit détectée avant
 * d'atteindre un utilisateur réel.
 */
class PatientTimelinePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_medecin_role_can_view_the_timeline_of_any_patient_in_their_structure(): void
    {
        $structure = Structure::factory()->create();
        $medecin = User::factory()->for($structure)->create();
        $medecin->assignRole('medecin');

        // Simule à la fois un patient "ancien" (créé en premier) et un
        // patient créé plus récemment (comme la patiente signalée dans le
        // bug), pour couvrir les deux cas cités dans le rapport.
        $oldPatient = Patient::factory()->for($structure)->create();
        $recentPatient = Patient::factory()->for($structure)->create();

        $this->actingAs($medecin)
            ->getJson("/api/patients/{$oldPatient->id}/timeline")
            ->assertOk();

        $this->actingAs($medecin)
            ->getJson("/api/patients/{$recentPatient->id}/timeline")
            ->assertOk();
    }

    public function test_administrateur_role_can_view_the_timeline_of_any_patient_in_their_structure(): void
    {
        $structure = Structure::factory()->create();
        $admin = User::factory()->for($structure)->create();
        $admin->assignRole('administrateur');

        $patient = Patient::factory()->for($structure)->create();

        $this->actingAs($admin)
            ->getJson("/api/patients/{$patient->id}/timeline")
            ->assertOk();
    }

    /**
     * secretaire est volontairement exclu de patients_medical.view dans
     * RolePermissionSeeder (accès administratif au dossier patient, pas au
     * contenu médical) — ce test fige ce comportement intentionnel et
     * vérifie, ce qui est le second axe du bug signalé, que le message
     * renvoyé est un message clair en français plutôt que le message brut
     * "User does not have the right permissions." de Spatie (voir le
     * gestionnaire renderable() dans bootstrap/app.php).
     */
    public function test_secretaire_role_is_blocked_from_the_timeline_with_a_clear_message(): void
    {
        $structure = Structure::factory()->create();
        $secretaire = User::factory()->for($structure)->create();
        $secretaire->assignRole('secretaire');

        $patient = Patient::factory()->for($structure)->create();

        $response = $this->actingAs($secretaire)
            ->getJson("/api/patients/{$patient->id}/timeline")
            ->assertForbidden();

        $response->assertJsonPath('message', "Vous n'avez pas les autorisations nécessaires pour effectuer cette action.");
        $response->assertJsonMissingPath('exception');
        $response->assertJsonMissingPath('trace');
    }

    /**
     * Non-régression directe pour l'hypothèse retenue pendant
     * l'investigation : Spatie::syncPermissions() (appelé par
     * RolePermissionSeeder) flush le cache global de permissions à chaque
     * exécution. Un ré-échantillonnage du seeder (ex. pour corriger un
     * autre rôle, comme cela a été fait pour directeur_medical dans cette
     * même session) ne doit jamais faire perdre à medecin l'accès à la
     * timeline, même juste après le flush.
     */
    public function test_medecin_still_has_timeline_access_immediately_after_a_role_permission_reseed(): void
    {
        $structure = Structure::factory()->create();
        $medecin = User::factory()->for($structure)->create();
        $medecin->assignRole('medecin');
        $patient = Patient::factory()->for($structure)->create();

        $this->actingAs($medecin)
            ->getJson("/api/patients/{$patient->id}/timeline")
            ->assertOk();

        // Re-exécute le seeder, comme lors d'un correctif touchant un
        // autre rôle — ceci flush le cache de permissions de Spatie.
        $this->seed(RolePermissionSeeder::class);

        $this->actingAs($medecin)
            ->getJson("/api/patients/{$patient->id}/timeline")
            ->assertOk();
    }
}
