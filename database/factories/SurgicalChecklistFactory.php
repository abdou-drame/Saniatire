<?php

namespace Database\Factories;

use App\Domain\BlocOperatoire\Models\SurgicalChecklist;
use App\Domain\BlocOperatoire\Models\SurgicalProcedure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurgicalChecklist>
 */
class SurgicalChecklistFactory extends Factory
{
    protected $model = SurgicalChecklist::class;

    public function definition(): array
    {
        return [
            'surgical_procedure_id' => SurgicalProcedure::factory(),
            'step' => fake()->randomElement(['avant_anesthesie', 'avant_incision', 'avant_sortie_bloc']),
            'items' => ['identite_confirmee' => true, 'site_marque' => true],
        ];
    }
}
