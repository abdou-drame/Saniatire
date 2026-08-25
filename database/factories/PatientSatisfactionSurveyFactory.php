<?php

namespace Database\Factories;

use App\Domain\Patient\Models\Patient;
use App\Domain\Qualite\Models\PatientSatisfactionSurvey;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientSatisfactionSurvey>
 */
class PatientSatisfactionSurveyFactory extends Factory
{
    protected $model = PatientSatisfactionSurvey::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'patient_id' => Patient::factory(),
            'service' => 'consultation',
            'note' => $this->faker->numberBetween(1, 10),
            'commentaire' => $this->faker->optional()->sentence(),
            'date' => $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
        ];
    }
}
