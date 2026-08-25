<?php

namespace Database\Factories;

use App\Domain\Imagerie\Models\ImagingReport;
use App\Domain\Imagerie\Models\ImagingStudy;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImagingReport>
 */
class ImagingReportFactory extends Factory
{
    protected $model = ImagingReport::class;

    public function definition(): array
    {
        return [
            'imaging_study_id' => ImagingStudy::factory(),
            'author_id' => User::factory(),
            'content' => fake()->paragraph(),
            'status' => 'brouillon',
        ];
    }
}
