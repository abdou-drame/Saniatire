<?php

namespace Tests\Feature;

use App\Domain\Facturation\Models\Invoice;
use App\Domain\Patient\Models\Patient;
use App\Domain\Pharmacie\Models\Product;
use App\Domain\Prescripteur\Models\ExternalPrescriber;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vérification étape 7b, point 1 du prompt d'audit : étanchéité stricte des
 * trois guards (sanctum/patient/prescriber). Chaque test présente un token
 * d'un guard sur des endpoints réels du/des deux autres guards, y compris
 * des endpoints sensibles explicitement demandés (dossier d'un autre
 * patient, facturation, gestion des stocks) et les deux portails externes
 * l'un contre l'autre. Chaque cas doit être rejeté sur la base de
 * l'identité du token (le provider Sanctum du guard), jamais sur un
 * paramètre d'URL.
 *
 * Note méthodologique : chaque cas de rejet croisé est isolé dans sa propre
 * méthode de test. Le guard résolu par Laravel (`RequestGuard`) est
 * mémorisé pour la durée de vie du conteneur applicatif partagé par une
 * méthode de test ; combiner plusieurs vérifications de guards différents
 * dans une seule méthode peut donc produire un faux négatif purement lié au
 * test harness (chaque requête HTTP réelle en production est un processus
 * indépendant, donc ce problème n'existe pas en dehors des tests).
 */
class CrossGuardIsolationTest extends TestCase
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

    public function test_a_patient_token_cannot_read_another_patients_record_via_staff_route(): void
    {
        $patient = Patient::factory()->withPortalActivated()->for($this->structure)->create();
        $otherPatient = Patient::factory()->for($this->structure)->create();
        $patientToken = $patient->createToken('patient-portal')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$patientToken}")
            ->getJson("/api/patients/{$otherPatient->id}")
            ->assertUnauthorized();
    }

    public function test_a_patient_token_cannot_reach_billing_endpoints(): void
    {
        $patient = Patient::factory()->withPortalActivated()->for($this->structure)->create();
        $patientToken = $patient->createToken('patient-portal')->plainTextToken;

        Invoice::factory()->for($this->structure)->for($this->site)->create(['patient_id' => $patient->id]);

        $this->withHeader('Authorization', "Bearer {$patientToken}")
            ->getJson('/api/invoices')
            ->assertUnauthorized();
    }

    public function test_a_patient_token_cannot_reach_stock_management_endpoints(): void
    {
        $patient = Patient::factory()->withPortalActivated()->for($this->structure)->create();
        $patientToken = $patient->createToken('patient-portal')->plainTextToken;

        Product::factory()->for($this->structure)->create();

        $this->withHeader('Authorization', "Bearer {$patientToken}")
            ->getJson('/api/products')
            ->assertUnauthorized();
    }

    public function test_a_prescriber_token_cannot_reach_billing_endpoints(): void
    {
        $prescriber = ExternalPrescriber::factory()->withPortalActivated()->for($this->structure)->create();
        $prescriberToken = $prescriber->createToken('prescriber-portal')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$prescriberToken}")
            ->getJson('/api/invoices')
            ->assertUnauthorized();
    }

    public function test_a_prescriber_token_cannot_reach_stock_management_endpoints(): void
    {
        $prescriber = ExternalPrescriber::factory()->withPortalActivated()->for($this->structure)->create();
        $prescriberToken = $prescriber->createToken('prescriber-portal')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$prescriberToken}")
            ->getJson('/api/products')
            ->assertUnauthorized();
    }

    public function test_a_patient_token_is_rejected_on_the_prescriber_portal(): void
    {
        $patient = Patient::factory()->withPortalActivated()->for($this->structure)->create();
        $patientToken = $patient->createToken('patient-portal')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$patientToken}")
            ->getJson('/api/portail-prescripteur/me')
            ->assertUnauthorized();
    }

    public function test_a_prescriber_token_is_rejected_on_the_patient_portal(): void
    {
        $prescriber = ExternalPrescriber::factory()->withPortalActivated()->for($this->structure)->create();
        $prescriberToken = $prescriber->createToken('prescriber-portal')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$prescriberToken}")
            ->getJson('/api/portail-patient/me')
            ->assertUnauthorized();
    }

    public function test_an_invalid_or_foreign_bearer_token_is_rejected_everywhere(): void
    {
        $this->withHeader('Authorization', 'Bearer 999999|ceci-nest-pas-un-token-valide')
            ->getJson('/api/portail-patient/me')
            ->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer 999999|ceci-nest-pas-un-token-valide')
            ->getJson('/api/portail-prescripteur/me')
            ->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer 999999|ceci-nest-pas-un-token-valide')
            ->getJson('/api/patients')
            ->assertUnauthorized();
    }

    public function test_a_deactivated_prescribers_existing_token_is_rejected(): void
    {
        $prescriber = ExternalPrescriber::factory()->withPortalActivated()->for($this->structure)->create(['statut' => 'inactif']);
        $prescriberToken = $prescriber->createToken('prescriber-portal')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$prescriberToken}")
            ->getJson('/api/portail-prescripteur/me')
            ->assertUnauthorized();
    }
}
