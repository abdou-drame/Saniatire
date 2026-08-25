<?php

namespace Database\Factories;

use App\Domain\Pharmacie\Models\Product;
use App\Domain\Pharmacie\Models\StockThreshold;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockThreshold>
 */
class StockThresholdFactory extends Factory
{
    protected $model = StockThreshold::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'product_id' => Product::factory(),
            'site_id' => Site::factory(),
            'seuil_minimum' => 20,
        ];
    }
}
