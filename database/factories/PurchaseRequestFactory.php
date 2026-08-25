<?php

namespace Database\Factories;

use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Pharmacie\Models\Product;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequest>
 */
class PurchaseRequestFactory extends Factory
{
    protected $model = PurchaseRequest::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'site_id' => Site::factory(),
            'product_id' => Product::factory(),
            'demandeur_id' => User::factory(),
            'quantite' => 10,
            'statut' => 'demandee',
        ];
    }
}
