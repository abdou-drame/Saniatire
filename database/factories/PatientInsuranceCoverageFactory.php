<?php

namespace Database\Factories;

use App\Domain\Assurance\Models\InsuranceConvention;
use App\Domain\Assurance\Models\PatientInsuranceCoverage;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientInsuranceCoverage>
 */
class PatientInsuranceCoverageFactory extends Factory
{
    protected $model = PatientInsuranceCoverage::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'patient_id' => Patient::factory(),
            'insurance_convention_id' => InsuranceConvention::factory(),
            'numero_adherent' => $this->faker->unique()->numerify('ADH-#####'),
            'beneficiaire_type' => 'assure_principal',
            'date_debut' => now()->subYear()->toDateString(),
            'date_fin' => null,
            'actif' => true,
        ];
    }
}
