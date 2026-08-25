<?php

namespace Database\Factories;

use App\Domain\Laboratoire\Models\LoincCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoincCode>
 */
class LoincCodeFactory extends Factory
{
    protected $model = LoincCode::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('#####-#'),
            'label' => fake()->sentence(3),
            'component' => fake()->word(),
            'default_unit' => 'mg/L',
            'version' => 'LOINC',
            'status' => 'actif',
        ];
    }
}
