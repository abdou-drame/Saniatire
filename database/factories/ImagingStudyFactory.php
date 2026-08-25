<?php

namespace Database\Factories;

use App\Domain\Imagerie\Models\ImagingOrder;
use App\Domain\Imagerie\Models\ImagingStudy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImagingStudy>
 */
class ImagingStudyFactory extends Factory
{
    protected $model = ImagingStudy::class;

    public function definition(): array
    {
        return [
            'imaging_order_id' => ImagingOrder::factory(),
            'study_instance_uid' => '1.2.826.0.1.'.fake()->unique()->numerify('##########'),
            'accession_number' => 'ACC-'.fake()->unique()->numerify('########'),
            'modality' => fake()->randomElement(['CR', 'US', 'CT', 'MR']),
            'performed_at' => now(),
            'status' => 'realise',
        ];
    }
}
