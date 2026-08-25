<?php

namespace Database\Factories;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Consultation\Models\ConsultationDiagnosis;
use App\Domain\Icd\Models\IcdCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsultationDiagnosis>
 */
class ConsultationDiagnosisFactory extends Factory
{
    protected $model = ConsultationDiagnosis::class;

    public function definition(): array
    {
        return [
            'consultation_id' => Consultation::factory(),
            'icd_code_id' => IcdCode::factory(),
            'code_snapshot' => strtoupper(fake()->bothify('?##')),
            'label_snapshot' => fake()->sentence(3),
            'version_snapshot' => 'CIM-10',
            'type' => 'principal',
            'status' => 'provisoire',
        ];
    }
}
