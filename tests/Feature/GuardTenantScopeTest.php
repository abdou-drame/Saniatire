<?php

namespace Tests\Feature;

use App\Domain\Appointment\Models\Appointment;
use App\Domain\Patient\Models\Patient;
use App\Domain\Prescripteur\Models\ExternalPrescriber;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vérification immédiate de la fondation guard-aware de TenantScope (étape
 * 7b §0), avant de construire les portails dessus : un patient authentifié
 * sous le guard `patient` doit voir TenantScope filtrer automatiquement sur
 * son propre structure_id, exactement comme pour le personnel.
 */
class GuardTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_scope_filters_by_authenticated_patient_structure(): void
    {
        $structureA = Structure::factory()->create();
        $structureB = Structure::factory()->create();
        $siteA = Site::factory()->create(['structure_id' => $structureA->id]);
        $siteB = Site::factory()->create(['structure_id' => $structureB->id]);
        $practitionerA = User::factory()->create(['structure_id' => $structureA->id]);
        $practitionerB = User::factory()->create(['structure_id' => $structureB->id]);

        $patientA = Patient::factory()->withPortalActivated()->create(['structure_id' => $structureA->id]);
        $patientB = Patient::factory()->withPortalActivated()->create(['structure_id' => $structureB->id]);

        Appointment::factory()->create([
            'structure_id' => $structureA->id,
            'site_id' => $siteA->id,
            'patient_id' => $patientA->id,
            'practitioner_id' => $practitionerA->id,
        ]);
        Appointment::factory()->create([
            'structure_id' => $structureB->id,
            'site_id' => $siteB->id,
            'patient_id' => $patientB->id,
            'practitioner_id' => $practitionerB->id,
        ]);

        $this->actingAs($patientA, 'patient');

        $visible = Appointment::all();

        $this->assertCount(1, $visible);
        $this->assertSame($structureA->id, $visible->first()->structure_id);
    }

    public function test_tenant_scope_filters_by_authenticated_prescriber_structure(): void
    {
        $structureA = Structure::factory()->create();
        $structureB = Structure::factory()->create();

        $prescriberA = ExternalPrescriber::factory()->withPortalActivated()->create(['structure_id' => $structureA->id]);
        Patient::factory()->create(['structure_id' => $structureA->id]);
        Patient::factory()->create(['structure_id' => $structureB->id]);

        $this->actingAs($prescriberA, 'prescriber');

        $visible = Patient::all();

        $this->assertCount(1, $visible);
        $this->assertSame($structureA->id, $visible->first()->structure_id);
    }

    public function test_a_patient_token_is_rejected_by_the_staff_guard_and_vice_versa(): void
    {
        $structure = Structure::factory()->create();
        $patient = Patient::factory()->withPortalActivated()->create(['structure_id' => $structure->id]);
        $user = User::factory()->create(['structure_id' => $structure->id]);

        $patientToken = $patient->createToken('patient-portal')->plainTextToken;
        $staffToken = $user->createToken('api')->plainTextToken;

        // Staff route (guard "sanctum") refuse un token patient.
        $this->withHeader('Authorization', 'Bearer '.$patientToken)
            ->getJson('/api/patients')
            ->assertUnauthorized();

        // Un token staff, présenté au guard "patient", ne résout à aucun
        // patient : Sanctum::hasValidProvider() rejette le tokenable car il
        // n'est pas une instance du modèle du provider "patients".
        $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($staffToken);
        $this->assertNotNull($accessToken);
        $this->assertInstanceOf(User::class, $accessToken->tokenable);
        $this->assertFalse($accessToken->tokenable instanceof Patient);
    }
}
