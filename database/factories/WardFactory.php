<?php

namespace Database\Factories;

use App\Domain\Hospitalisation\Models\Ward;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ward>
 */
class WardFactory extends Factory
{
    protected $model = Ward::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'site_id' => Site::factory(),
            'name' => fake()->randomElement(['Médecine interne', 'Chirurgie', 'Pédiatrie', 'Maternité', 'Réanimation']),
        ];
    }
}
