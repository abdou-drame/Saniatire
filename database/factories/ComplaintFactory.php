<?php

namespace Database\Factories;

use App\Domain\Patient\Models\Patient;
use App\Domain\Qualite\Models\Complaint;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Complaint>
 */
class ComplaintFactory extends Factory
{
    protected $model = Complaint::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'patient_id' => Patient::factory(),
            'motif' => $this->faker->randomElement(['attente', 'accueil', 'facturation', 'soins']),
            'description' => $this->faker->paragraph(),
            'service_concerne' => 'consultation',
            'statut' => 'ouverte',
        ];
    }
}
