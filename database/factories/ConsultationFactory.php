<?php

namespace Database\Factories;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consultation>
 */
class ConsultationFactory extends Factory
{
    protected $model = Consultation::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'patient_id' => Patient::factory(),
            'practitioner_id' => User::factory(),
            'site_id' => Site::factory(),
            'reason' => fake()->sentence(4),
            'history_of_illness' => fake()->paragraph(),
            'weight_kg' => fake()->randomFloat(2, 3, 120),
            'height_cm' => fake()->randomFloat(1, 45, 200),
            'temperature_c' => fake()->randomFloat(1, 36, 39),
            'blood_pressure_systolic' => fake()->numberBetween(90, 150),
            'blood_pressure_diastolic' => fake()->numberBetween(60, 95),
            'heart_rate' => fake()->numberBetween(55, 110),
            'respiratory_rate' => fake()->numberBetween(12, 22),
            'spo2' => fake()->numberBetween(92, 100),
            'pain_scale' => fake()->numberBetween(0, 10),
            'status' => 'en_cours',
        ];
    }
}
