<?php

namespace Database\Factories;

use App\Domain\Hospitalisation\Models\Bed;
use App\Domain\Hospitalisation\Models\Hospitalization;
use App\Domain\Hospitalisation\Models\Ward;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hospitalization>
 */
class HospitalizationFactory extends Factory
{
    protected $model = Hospitalization::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'site_id' => Site::factory(),
            'patient_id' => Patient::factory(),
            'bed_id' => Bed::factory(),
            'ward_id' => Ward::factory(),
            'attending_physician_id' => User::factory(),
            'admitted_at' => now(),
            'admission_reason' => fake()->sentence(),
            'status' => 'en_cours',
        ];
    }
}
