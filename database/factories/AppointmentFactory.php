<?php

namespace Database\Factories;

use App\Domain\Appointment\Models\Appointment;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'site_id' => Site::factory(),
            'patient_id' => Patient::factory(),
            'practitioner_id' => User::factory(),
            'starts_at' => fake()->dateTimeBetween('+1 day', '+2 weeks'),
            'duration_minutes' => fake()->randomElement([15, 30, 45]),
            'reason' => fake()->sentence(4),
            'status' => 'planifie',
            'is_recurring' => false,
        ];
    }
}
