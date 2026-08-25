<?php

namespace Tests\Feature;

use App\Domain\Ai\Contracts\AiProvider;
use App\Domain\Consultation\Models\Consultation;
use App\Domain\Laboratoire\Models\LabOrder;
use App\Domain\Laboratoire\Models\LabOrderItem;
use App\Domain\Laboratoire\Models\LabResult;
use App\Domain\Laboratoire\Models\LoincCode;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Step9AiAssistanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function makeConsultation(Structure $structure, array $overrides = []): Consultation
    {
        $site = Site::factory()->for($structure)->create();
        $patient = Patient::factory()->for($structure)->create();
        $doctor = User::factory()->for($structure)->create();
        $doctor->assignRole('medecin');

        return Consultation::factory()
            ->for($structure)
            ->for($patient)
            ->for($doctor, 'practitioner')
            ->for($site)
            ->create($overrides);
    }

    public function test_ai_summary_response_declares_persisted_false_and_the_consultation_is_never_modified(): void
    {
        $structure = Structure::factory()->create();
        $consultation = $this->makeConsultation($structure, [
            'reason' => 'Douleur thoracique',
            'recommendations' => null,
        ]);

        $originalUpdatedAt = $consultation->updated_at;
        $originalRecommendations = $consultation->recommendations;

        $response = $this->actingAs($consultation->practitioner)
            ->postJson("/api/consultations/{$consultation->id}/ai-summary")
            ->assertOk();

        $this->assertFalse($response->json('persisted'));
        $this->assertNotEmpty($response->json('summary'));

        $consultation->refresh();
        $this->assertSame($originalRecommendations, $consultation->recommendations);
        $this->assertTrue($consultation->updated_at->equalTo($originalUpdatedAt));
    }

    public function test_ai_summary_degraded_mode_without_api_key_still_responds_200(): void
    {
        config(['services.anthropic.key' => null]);

        $structure = Structure::factory()->create();
        $consultation = $this->makeConsultation($structure, ['reason' => 'Fièvre persistante']);

        $response = $this->actingAs($consultation->practitioner)
            ->postJson("/api/consultations/{$consultation->id}/ai-summary")
            ->assertOk();

        $this->assertStringContainsString('simulé', $response->json('summary'));
        $this->assertFalse($response->json('persisted'));
    }

    public function test_ai_summary_returns_503_without_blocking_when_the_provider_throws(): void
    {
        $this->app->bind(AiProvider::class, function () {
            return new class implements AiProvider
            {
                public function summarizeConsultation(array $context): string
                {
                    throw new \RuntimeException('Panne simulée du fournisseur IA.');
                }
            };
        });

        $structure = Structure::factory()->create();
        $consultation = $this->makeConsultation($structure);

        $this->actingAs($consultation->practitioner)
            ->postJson("/api/consultations/{$consultation->id}/ai-summary")
            ->assertStatus(503);
    }

    public function test_ai_summary_requires_the_dedicated_permission(): void
    {
        $structure = Structure::factory()->create();
        $consultation = $this->makeConsultation($structure);

        $secretary = User::factory()->for($structure)->create();
        $secretary->assignRole('secretaire');

        $this->actingAs($secretary)
            ->postJson("/api/consultations/{$consultation->id}/ai-summary")
            ->assertForbidden();
    }

    public function test_anomaly_endpoint_flags_out_of_range_vitals_using_plain_comparison(): void
    {
        $structure = Structure::factory()->create();
        $consultation = $this->makeConsultation($structure, [
            'temperature_c' => 39.5, // hors plage 36.1-37.2
            'heart_rate' => 130,     // hors plage 60-100
            'respiratory_rate' => 16, // dans la plage
            'spo2' => 98,             // dans la plage
            'blood_pressure_systolic' => 120,
            'blood_pressure_diastolic' => 80,
        ]);

        $response = $this->actingAs($consultation->practitioner)
            ->getJson("/api/consultations/{$consultation->id}/anomalies")
            ->assertOk();

        $this->assertSame('comparaison_simple', $response->json('method'));

        $vitalFields = collect($response->json('anomalies.vitals'))->pluck('field')->all();
        $this->assertContains('temperature_c', $vitalFields);
        $this->assertContains('heart_rate', $vitalFields);
        $this->assertNotContains('respiratory_rate', $vitalFields);
        $this->assertNotContains('spo2', $vitalFields);
        $this->assertCount(2, $vitalFields);
    }

    public function test_anomaly_endpoint_flags_out_of_range_and_critical_lab_results(): void
    {
        $structure = Structure::factory()->create();
        $consultation = $this->makeConsultation($structure);

        $labOrder = LabOrder::factory()
            ->for($structure)
            ->for($consultation->patient)
            ->create(['consultation_id' => $consultation->id]);

        $loinc = LoincCode::factory()->create(['label' => 'Créatinine']);
        $outOfRangeItem = LabOrderItem::factory()->for($labOrder)->for($loinc, 'loincCode')->create();
        LabResult::factory()->for($outOfRangeItem, 'orderItem')->create([
            'value' => '25',
            'reference_min' => 0,
            'reference_max' => 10,
            'interpretation' => 'normal',
        ]);

        $loincNormal = LoincCode::factory()->create(['label' => 'Sodium']);
        $normalItem = LabOrderItem::factory()->for($labOrder)->for($loincNormal, 'loincCode')->create();
        LabResult::factory()->for($normalItem, 'orderItem')->create([
            'value' => '5',
            'reference_min' => 0,
            'reference_max' => 10,
            'interpretation' => 'normal',
        ]);

        $loincCritical = LoincCode::factory()->create(['label' => 'Potassium']);
        $criticalItem = LabOrderItem::factory()->for($labOrder)->for($loincCritical, 'loincCode')->create();
        LabResult::factory()->for($criticalItem, 'orderItem')->create([
            'value' => '4',
            'reference_min' => 0,
            'reference_max' => 10,
            'interpretation' => 'critique',
        ]);

        $response = $this->actingAs($consultation->practitioner)
            ->getJson("/api/consultations/{$consultation->id}/anomalies")
            ->assertOk();

        $labels = collect($response->json('anomalies.lab_results'))->pluck('label')->all();
        $this->assertContains('Créatinine', $labels);
        $this->assertContains('Potassium', $labels);
        $this->assertNotContains('Sodium', $labels);
        $this->assertCount(2, $labels);
    }

    public function test_anomaly_endpoint_requires_the_dedicated_permission(): void
    {
        $structure = Structure::factory()->create();
        $consultation = $this->makeConsultation($structure);

        $secretary = User::factory()->for($structure)->create();
        $secretary->assignRole('secretaire');

        $this->actingAs($secretary)
            ->getJson("/api/consultations/{$consultation->id}/anomalies")
            ->assertForbidden();
    }
}
