<?php

namespace Database\Factories;

use App\Domain\Rh\Models\EmployeeProfile;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeProfile>
 */
class EmployeeProfileFactory extends Factory
{
    protected $model = EmployeeProfile::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'user_id' => User::factory(),
            'date_embauche' => now()->subYears(2)->toDateString(),
            'type_contrat' => 'cdi',
            'statut_emploi' => 'actif',
            'qualification' => 'Médecin généraliste',
            'numero_ordre' => null,
        ];
    }
}
