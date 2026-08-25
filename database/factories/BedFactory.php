<?php

namespace Database\Factories;

use App\Domain\Hospitalisation\Models\Bed;
use App\Domain\Hospitalisation\Models\Ward;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bed>
 */
class BedFactory extends Factory
{
    protected $model = Bed::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'site_id' => Site::factory(),
            'ward_id' => Ward::factory(),
            'room_number' => (string) fake()->numberBetween(100, 499),
            'bed_label' => fake()->randomElement(['A', 'B', 'C']),
            'status' => 'libre',
        ];
    }
}
