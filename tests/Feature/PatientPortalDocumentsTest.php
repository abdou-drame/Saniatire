<?php

namespace Tests\Feature;

use App\Domain\Imagerie\Models\ImagingOrder;
use App\Domain\Imagerie\Models\ImagingReport;
use App\Domain\Imagerie\Models\ImagingStudy;
use App\Domain\Laboratoire\Models\LabOrder;
use App\Domain\Laboratoire\Models\LabOrderItem;
use App\Domain\Laboratoire\Models\LabResult;
use App\Domain\Laboratoire\Models\LabSample;
use App\Domain\Laboratoire\Models\LoincCode;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientPortalDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structureA;

    private Patient $patientA;

    private Patient $otherPatientA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->structureA = Structure::factory()->create();
        $this->patientA = Patient::factory()->withPortalActivated()->for($this->structureA)->create();
        $this->otherPatientA = Patient::factory()->withPortalActivated()->for($this->structureA)->create();
    }

    private function makeLabResult(Patient $patient, string $status): LabResult
    {
        $requester = User::factory()->for($this->structureA)->create();

        $labOrder = LabOrder::factory()->for($this->structureA)->create([
            'patient_id' => $patient->id,
            'requester_id' => $requester->id,
        ]);
        $loincCode = LoincCode::factory()->create(['label' => 'Glycémie à jeun']);
        $orderItem = LabOrderItem::factory()->create([
            'lab_order_id' => $labOrder->id,
            'loinc_code_id' => $loincCode->id,
        ]);
        $sample = LabSample::factory()->create([
            'lab_order_id' => $labOrder->id,
            'collected_by' => $requester->id,
        ]);

        return LabResult::factory()->create([
            'lab_sample_id' => $sample->id,
            'lab_order_item_id' => $orderItem->id,
            'status' => $status,
        ]);
    }

    private function makeImagingReport(Patient $patient, string $studyStatus, string $reportStatus): ImagingReport
    {
        $requester = User::factory()->for($this->structureA)->create();

        $imagingOrder = ImagingOrder::factory()->for($this->structureA)->create([
            'patient_id' => $patient->id,
            'requester_id' => $requester->id,
        ]);
        $study = ImagingStudy::factory()->create([
            'imaging_order_id' => $imagingOrder->id,
            'status' => $studyStatus,
        ]);

        return ImagingReport::factory()->create([
            'imaging_study_id' => $study->id,
            'author_id' => $requester->id,
            'status' => $reportStatus,
        ]);
    }

    public function test_a_patient_sees_only_fully_transmitted_lab_results_never_pending_ones(): void
    {
        $transmitted = $this->makeLabResult($this->patientA, 'transmis');
        $pending = $this->makeLabResult($this->patientA, 'validation_technique_attente');

        $response = $this->actingAs($this->patientA, 'patient')
            ->getJson('/api/portail-patient/documents')
            ->assertOk();

        $ids = collect($response->json('resultats_laboratoire'))->pluck('id');
        $this->assertTrue($ids->contains($transmitted->id));
        $this->assertFalse($ids->contains($pending->id));
    }

    public function test_a_patient_never_sees_another_patients_lab_results(): void
    {
        $others = $this->makeLabResult($this->otherPatientA, 'transmis');

        $response = $this->actingAs($this->patientA, 'patient')
            ->getJson('/api/portail-patient/documents')
            ->assertOk();

        $ids = collect($response->json('resultats_laboratoire'))->pluck('id');
        $this->assertFalse($ids->contains($others->id));
    }

    public function test_a_patient_sees_only_fully_validated_imaging_reports_never_unvalidated_ones(): void
    {
        $validated = $this->makeImagingReport($this->patientA, 'transmis', 'valide');
        $notYetValidated = $this->makeImagingReport($this->patientA, 'transmis', 'brouillon');
        $notYetTransmitted = $this->makeImagingReport($this->patientA, 'realise', 'valide');

        $response = $this->actingAs($this->patientA, 'patient')
            ->getJson('/api/portail-patient/documents')
            ->assertOk();

        $ids = collect($response->json('comptes_rendus_imagerie'))->pluck('id');
        $this->assertTrue($ids->contains($validated->id));
        $this->assertFalse($ids->contains($notYetValidated->id));
        $this->assertFalse($ids->contains($notYetTransmitted->id));
    }

    public function test_a_patient_never_sees_another_patients_imaging_reports(): void
    {
        $others = $this->makeImagingReport($this->otherPatientA, 'transmis', 'valide');

        $response = $this->actingAs($this->patientA, 'patient')
            ->getJson('/api/portail-patient/documents')
            ->assertOk();

        $ids = collect($response->json('comptes_rendus_imagerie'))->pluck('id');
        $this->assertFalse($ids->contains($others->id));
    }

    public function test_documents_are_isolated_across_structures(): void
    {
        $structureB = Structure::factory()->create();
        $patientB = Patient::factory()->withPortalActivated()->for($structureB)->create();
        $requesterB = User::factory()->for($structureB)->create();

        $labOrderB = LabOrder::factory()->for($structureB)->create([
            'patient_id' => $patientB->id,
            'requester_id' => $requesterB->id,
        ]);
        $loincCode = LoincCode::factory()->create();
        $orderItemB = LabOrderItem::factory()->create([
            'lab_order_id' => $labOrderB->id,
            'loinc_code_id' => $loincCode->id,
        ]);
        $sampleB = LabSample::factory()->create([
            'lab_order_id' => $labOrderB->id,
            'collected_by' => $requesterB->id,
        ]);
        $resultB = LabResult::factory()->create([
            'lab_sample_id' => $sampleB->id,
            'lab_order_item_id' => $orderItemB->id,
            'status' => 'transmis',
        ]);

        $response = $this->actingAs($this->patientA, 'patient')
            ->getJson('/api/portail-patient/documents')
            ->assertOk();

        $ids = collect($response->json('resultats_laboratoire'))->pluck('id');
        $this->assertFalse($ids->contains($resultB->id));
    }
}
