<?php

namespace Database\Factories;

use App\Domain\Pharmacie\Models\Product;
use App\Domain\Pharmacie\Models\ProductBatch;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductBatch>
 */
class ProductBatchFactory extends Factory
{
    protected $model = ProductBatch::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'product_id' => Product::factory(),
            'site_id' => Site::factory(),
            'supplier_id' => null,
            'numero_lot' => strtoupper(fake()->bothify('LOT-####??')),
            'date_peremption' => now()->addYear()->toDateString(),
            'quantite_stock' => 100,
            'prix_achat_unitaire' => fake()->randomFloat(2, 100, 5000),
        ];
    }
}
