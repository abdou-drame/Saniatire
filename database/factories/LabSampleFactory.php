<?php

namespace Database\Factories;

use App\Domain\Laboratoire\Models\LabOrder;
use App\Domain\Laboratoire\Models\LabSample;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabSample>
 */
class LabSampleFactory extends Factory
{
    protected $model = LabSample::class;

    public function definition(): array
    {
        return [
            'lab_order_id' => LabOrder::factory(),
            'barcode' => fake()->unique()->numerify('SMP-########'),
            'sample_type' => 'sang',
            'collected_at' => now(),
            'collected_by' => User::factory(),
        ];
    }
}
