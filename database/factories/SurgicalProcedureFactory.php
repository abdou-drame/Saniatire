<?php

namespace Database\Factories;

use App\Domain\BlocOperatoire\Models\SurgicalProcedure;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurgicalProcedure>
 */
class SurgicalProcedureFactory extends Factory
{
    protected $model = SurgicalProcedure::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'site_id' => Site::factory(),
            'patient_id' => Patient::factory(),
            'surgeon_id' => User::factory(),
            'anesthesiologist_id' => User::factory(),
            'operating_room' => fake()->randomElement(['Bloc 1', 'Bloc 2', 'Bloc 3']),
            'procedure_type' => fake()->randomElement(['Appendicectomie', 'Cholécystectomie', 'Césarienne']),
            'scheduled_at' => now()->addDay(),
            'status' => 'planifiee',
        ];
    }
}
