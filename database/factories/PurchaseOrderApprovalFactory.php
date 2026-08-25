<?php

namespace Database\Factories;

use App\Domain\Achats\Models\PurchaseOrder;
use App\Domain\Achats\Models\PurchaseOrderApproval;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderApproval>
 */
class PurchaseOrderApprovalFactory extends Factory
{
    protected $model = PurchaseOrderApproval::class;

    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'approval_rule_id' => null,
            'level' => 1,
            'min_amount' => 0,
            'role_name' => 'gestionnaire_stock',
            'statut' => 'en_attente',
            'approved_by' => null,
            'approved_at' => null,
        ];
    }
}
