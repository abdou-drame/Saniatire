<?php

namespace Database\Factories;

use App\Domain\Achats\Models\PurchaseOrderItem;
use App\Domain\Achats\Models\PurchaseOrderReception;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderReception>
 */
class PurchaseOrderReceptionFactory extends Factory
{
    protected $model = PurchaseOrderReception::class;

    public function definition(): array
    {
        return [
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'receptionne_par' => User::factory(),
            'quantite_recue' => 10,
            'date_reception' => now()->toDateString(),
            'controle_qualite' => 'conforme',
            'numero_lot' => strtoupper(fake()->bothify('LOT-####??')),
            'date_peremption' => now()->addYear()->toDateString(),
        ];
    }
}
