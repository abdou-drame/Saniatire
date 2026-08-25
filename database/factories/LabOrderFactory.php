<?php

namespace Database\Factories;

use App\Domain\Laboratoire\Models\LabOrder;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabOrder>
 */
class LabOrderFactory extends Factory
{
    protected $model = LabOrder::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'site_id' => Site::factory(),
            'patient_id' => Patient::factory(),
            'requester_type' => User::class,
            'requester_id' => User::factory(),
            'status' => 'demande',
            'billing_status' => 'pending',
            'ordered_at' => now(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
