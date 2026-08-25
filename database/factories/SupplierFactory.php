<?php

namespace Database\Factories;

use App\Domain\Achats\Models\Supplier;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'nom' => fake()->company(),
            'contact' => fake()->phoneNumber(),
            'conditions_commerciales' => fake()->sentence(),
        ];
    }
}
