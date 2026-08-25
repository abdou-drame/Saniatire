<?php

namespace Database\Factories;

use App\Domain\Hospitalisation\Models\Hospitalization;
use App\Domain\Hospitalisation\Models\HospitalizationDailyNote;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HospitalizationDailyNote>
 */
class HospitalizationDailyNoteFactory extends Factory
{
    protected $model = HospitalizationDailyNote::class;

    public function definition(): array
    {
        return [
            'hospitalization_id' => Hospitalization::factory(),
            'author_id' => User::factory(),
            'note_date' => now()->toDateString(),
            'care_administered' => fake()->sentence(),
            'observations' => fake()->optional()->sentence(),
        ];
    }
}
