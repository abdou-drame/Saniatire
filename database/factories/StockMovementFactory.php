<?php

namespace Database\Factories;

use App\Domain\Pharmacie\Models\ProductBatch;
use App\Domain\Pharmacie\Models\StockMovement;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'product_batch_id' => ProductBatch::factory(),
            'site_id' => Site::factory(),
            'destination_site_id' => null,
            'user_id' => User::factory(),
            'type' => 'entree',
            'quantite' => 10,
            'motif' => fake()->sentence(),
            'dispensed_for_type' => null,
            'dispensed_for_id' => null,
        ];
    }
}
