<?php

namespace Database\Factories;

use App\Domain\Patient\Models\Patient;
use App\Domain\Patient\Models\PatientAllergy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientAllergy>
 */
class PatientAllergyFactory extends Factory
{
    protected $model = PatientAllergy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'allergen' => fake()->randomElement(['Pénicilline', 'Arachide', 'Latex', 'Pollen', 'Iode', 'Aspirine']),
            'severity' => fake()->randomElement(['low', 'moderate', 'high', 'critical']),
            'reaction' => fake()->optional()->sentence(),
        ];
    }
}
