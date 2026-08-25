<?php

namespace Database\Factories;

use App\Domain\Patient\Models\Patient;
use App\Domain\Patient\Models\PatientMedicalInfo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientMedicalInfo>
 */
class PatientMedicalInfoFactory extends Factory
{
    protected $model = PatientMedicalInfo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'blood_group' => fake()->randomElement(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']),
            'medical_history' => fake()->optional()->sentence(),
            'chronic_diseases' => fake()->optional()->sentence(),
            'current_treatments' => fake()->optional()->sentence(),
        ];
    }
}
