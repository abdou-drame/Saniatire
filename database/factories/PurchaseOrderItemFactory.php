<?php

namespace Database\Factories;

use App\Domain\Achats\Models\PurchaseOrder;
use App\Domain\Achats\Models\PurchaseOrderItem;
use App\Domain\Pharmacie\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'purchase_request_id' => null,
            'product_id' => Product::factory(),
            'quantite_commandee' => 10,
            'prix_unitaire' => 100,
            'quantite_recue' => 0,
        ];
    }
}
