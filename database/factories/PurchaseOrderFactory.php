<?php

namespace Database\Factories;

use App\Domain\Achats\Models\PurchaseOrder;
use App\Domain\Achats\Models\Supplier;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'site_id' => Site::factory(),
            'supplier_id' => Supplier::factory(),
            'created_by' => User::factory(),
            'montant_total' => 0,
            'statut' => 'brouillon',
        ];
    }
}
