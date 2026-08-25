<?php

namespace Database\Factories;

use App\Domain\Assurance\Models\InsuranceProvider;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InsuranceProvider>
 */
class InsuranceProviderFactory extends Factory
{
    protected $model = InsuranceProvider::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'nom' => $this->faker->company(),
            'type' => $this->faker->randomElement(['assurance_privee', 'ipm', 'mutuelle']),
            'contact' => $this->faker->phoneNumber(),
        ];
    }
}
