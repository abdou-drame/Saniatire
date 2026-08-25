<?php

namespace Database\Factories;

use App\Domain\Laboratoire\Models\LabOrder;
use App\Domain\Laboratoire\Models\LabOrderItem;
use App\Domain\Laboratoire\Models\LoincCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabOrderItem>
 */
class LabOrderItemFactory extends Factory
{
    protected $model = LabOrderItem::class;

    public function definition(): array
    {
        return [
            'lab_order_id' => LabOrder::factory(),
            'loinc_code_id' => LoincCode::factory(),
            'status' => 'demande',
        ];
    }
}
