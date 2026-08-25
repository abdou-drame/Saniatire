<?php

namespace Tests\Feature;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Consultation\Models\ConsultationDiagnosis;
use App\Domain\Laboratoire\Models\LabOrder;
use App\Domain\Laboratoire\Models\LabOrderItem;
use App\Domain\Laboratoire\Models\LabResult;
use App\Domain\Laboratoire\Models\LabSample;
use App\Domain\Laboratoire\Models\LoincCode;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FhirTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function makeReader(Structure $structure): User
    {
        $user = User::factory()->for($structure)->create();
        $user->assignRole('medecin');

        return $user;
    }

    public function test_patient_resource_has_required_fhir_r4_fields(): void
    {
        $structure = Structure::factory()->create();
        $reader = $this->makeReader($structure);
        $patient = Patient::factory()->for($structure)->create();

        $response = $this->actingAs($reader)->getJson("/api/fhir/Patient/{$patient->id}")->assertOk();

        $response->assertJsonPath('resourceType', 'Patient');
        $this->assertNotEmpty($response->json('id'));
        $this->assertNotEmpty($response->json('name.0.family'));
        $this->assertContains($response->json('gender'), ['male', 'female', 'unknown']);
        $this->assertNotEmpty($response->json('birthDate'));
    }

    public function test_encounter_resource_has_required_status_and_class(): void
    {
        $structure = Structure::factory()->create();
        $reader = $this->makeReader($structure);
        $patient = Patient::factory()->for($structure)->create();
        $consultation = Consultation::factory()
            ->for($structure)
            ->for($patient)
            ->for($reader, 'practitioner')
            ->create(['status' => 'en_cours']);

        $response = $this->actingAs($reader)->getJson("/api/fhir/Encounter/{$consultation->id}")->assertOk();

        $response->assertJsonPath('resourceType', 'Encounter');
        $response->assertJsonPath('status', 'in-progress');
        $this->assertNotEmpty($response->json('class.code'));
        $response->assertJsonPath('subject.reference', "Patient/{$patient->id}");
    }

    public function test_condition_resource_has_required_code_and_subject(): void
    {
        $structure = Structure::factory()->create();
        $reader = $this->makeReader($structure);
        $patient = Patient::factory()->for($structure)->create();
        $consultation = Consultation::factory()->for($structure)->for($patient)->for($reader, 'practitioner')->create();
        $diagnosis = ConsultationDiagnosis::factory()->for($consultation)->create([
            'code_snapshot' => 'J45',
            'label_snapshot' => 'Asthme',
        ]);

        $response = $this->actingAs($reader)->getJson("/api/fhir/Condition/{$diagnosis->id}")->assertOk();

        $response->assertJsonPath('resourceType', 'Condition');
        $this->assertNotEmpty($response->json('code.coding.0.code'));
        $response->assertJsonPath('subject.reference', "Patient/{$patient->id}");
    }

    public function test_observation_resource_has_required_status_and_code(): void
    {
        $structure = Structure::factory()->create();
        $reader = $this->makeReader($structure);
        $patient = Patient::factory()->for($structure)->create();
        $labOrder = LabOrder::factory()->for($structure)->for($patient)->create();
        $loinc = LoincCode::factory()->create(['code' => '2345-7', 'label' => 'Glucose']);
        $item = LabOrderItem::factory()->for($labOrder)->for($loinc, 'loincCode')->create();
        $sample = LabSample::factory()->for($labOrder)->create();
        $result = LabResult::factory()->for($item, 'orderItem')->for($sample, 'sample')->create([
            'status' => 'valide',
        ]);

        $response = $this->actingAs($reader)->getJson("/api/fhir/Observation/{$result->id}")->assertOk();

        $response->assertJsonPath('resourceType', 'Observation');
        $response->assertJsonPath('status', 'final');
        $response->assertJsonPath('code.coding.0.code', '2345-7');
    }

    public function test_diagnostic_report_composite_id_routes_to_lab_order(): void
    {
        $structure = Structure::factory()->create();
        $reader = $this->makeReader($structure);
        $patient = Patient::factory()->for($structure)->create();
        $labOrder = LabOrder::factory()->for($structure)->for($patient)->create(['status' => 'transmis']);

        $response = $this->actingAs($reader)->getJson("/api/fhir/DiagnosticReport/lab-{$labOrder->id}")->assertOk();

        $response->assertJsonPath('resourceType', 'DiagnosticReport');
        $response->assertJsonPath('id', "lab-{$labOrder->id}");
        $response->assertJsonPath('status', 'final');
        $this->assertNotEmpty($response->json('code'));
    }

    public function test_diagnostic_report_rejects_an_unrecognised_id_prefix(): void
    {
        $structure = Structure::factory()->create();
        $reader = $this->makeReader($structure);

        $this->actingAs($reader)
            ->getJson('/api/fhir/DiagnosticReport/999')
            ->assertNotFound();
    }

    public function test_service_request_composite_id_has_required_status_intent_subject(): void
    {
        $structure = Structure::factory()->create();
        $reader = $this->makeReader($structure);
        $patient = Patient::factory()->for($structure)->create();
        $labOrder = LabOrder::factory()->for($structure)->for($patient)->create(['status' => 'demande']);

        $response = $this->actingAs($reader)->getJson("/api/fhir/ServiceRequest/lab-{$labOrder->id}")->assertOk();

        $response->assertJsonPath('resourceType', 'ServiceRequest');
        $response->assertJsonPath('status', 'active');
        $response->assertJsonPath('intent', 'order');
        $response->assertJsonPath('subject.reference', "Patient/{$patient->id}");
    }

    public function test_fhir_endpoints_require_the_fhir_view_permission(): void
    {
        $structure = Structure::factory()->create();
        $patient = Patient::factory()->for($structure)->create();

        $secretary = User::factory()->for($structure)->create();
        $secretary->assignRole('secretaire');

        $this->actingAs($secretary)
            ->getJson("/api/fhir/Patient/{$patient->id}")
            ->assertForbidden();
    }

    public function test_fhir_patient_endpoint_is_isolated_per_tenant(): void
    {
        $structureA = Structure::factory()->create();
        $readerA = $this->makeReader($structureA);

        $structureB = Structure::factory()->create();
        $patientB = Patient::factory()->for($structureB)->create();

        $this->actingAs($readerA)
            ->getJson("/api/fhir/Patient/{$patientB->id}")
            ->assertNotFound();
    }

    public function test_fhir_patient_bundle_search_by_identifier(): void
    {
        $structure = Structure::factory()->create();
        $reader = $this->makeReader($structure);
        $patient = Patient::factory()->for($structure)->create();

        $response = $this->actingAs($reader)
            ->getJson("/api/fhir/Patient?identifier={$patient->patient_number}")
            ->assertOk();

        $response->assertJsonPath('resourceType', 'Bundle');
        $response->assertJsonPath('type', 'searchset');
        $this->assertSame((string) $patient->id, $response->json('entry.0.resource.id'));
    }
}
