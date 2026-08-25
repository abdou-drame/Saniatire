<?php

namespace Database\Factories;

use App\Domain\Assurance\Models\InsuranceConvention;
use App\Domain\Assurance\Models\InsuranceProvider;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InsuranceConvention>
 */
class InsuranceConventionFactory extends Factory
{
    protected $model = InsuranceConvention::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'insurance_provider_id' => InsuranceProvider::factory(),
            'nom' => 'Convention '.$this->faker->word(),
            'date_debut' => now()->subYear()->toDateString(),
            'date_fin' => null,
            'actif' => true,
        ];
    }
}
