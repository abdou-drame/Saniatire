<?php

namespace Database\Factories;

use App\Domain\Laboratoire\Models\LabOrderItem;
use App\Domain\Laboratoire\Models\LabResult;
use App\Domain\Laboratoire\Models\LabSample;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabResult>
 */
class LabResultFactory extends Factory
{
    protected $model = LabResult::class;

    public function definition(): array
    {
        return [
            'lab_sample_id' => LabSample::factory(),
            'lab_order_item_id' => LabOrderItem::factory(),
            'value' => (string) fake()->randomFloat(2, 0, 20),
            'unit' => 'mg/L',
            'reference_min' => 0,
            'reference_max' => 10,
            'interpretation' => 'normal',
            'status' => 'validation_technique_attente',
        ];
    }
}
