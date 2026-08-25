<?php

namespace Database\Factories;

use App\Domain\Facturation\Models\ServiceTariff;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceTariff>
 */
class ServiceTariffFactory extends Factory
{
    protected $model = ServiceTariff::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'code' => 'TARIF_'.strtoupper($this->faker->unique()->lexify('????')),
            'libelle' => $this->faker->words(3, true),
            'categorie' => 'consultation',
            'prix_unitaire' => $this->faker->randomFloat(2, 1000, 50000),
            'actif' => true,
        ];
    }
}
