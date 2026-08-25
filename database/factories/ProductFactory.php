<?php

namespace Database\Factories;

use App\Domain\Pharmacie\Models\Product;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'generic_catalog_ref' => null,
            'nom_commercial' => fake()->words(2, true),
            'dci' => fake()->word(),
            'forme_galenique' => fake()->randomElement(['comprime', 'sirop', 'injectable', 'pommade']),
            'dosage' => '500mg',
            'categorie' => fake()->randomElement(['medicament', 'consommable', 'dispositif_medical']),
            'unite_vente' => fake()->randomElement(['boite', 'flacon', 'unite']),
            'actif' => true,
        ];
    }
}
