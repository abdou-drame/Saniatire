<?php

namespace Database\Factories;

use App\Domain\Appointment\Models\AppointmentSeries;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentSeries>
 */
class AppointmentSeriesFactory extends Factory
{
    protected $model = AppointmentSeries::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'patient_id' => Patient::factory(),
            'practitioner_id' => User::factory(),
            'site_id' => Site::factory(),
            'recurrence_rule' => fake()->randomElement(['weekly', 'biweekly', 'monthly']),
            'occurrences_count' => fake()->numberBetween(2, 6),
            'duration_minutes' => fake()->randomElement([15, 30, 45]),
            'reason' => fake()->sentence(4),
        ];
    }
}
