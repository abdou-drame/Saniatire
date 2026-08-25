<?php

namespace Database\Factories;

use App\Domain\Patient\Models\Patient;
use App\Domain\Referral\Models\PatientReferral;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientReferral>
 */
class PatientReferralFactory extends Factory
{
    protected $model = PatientReferral::class;

    public function definition(): array
    {
        return [
            'structure_origine_id' => Structure::factory(),
            'structure_destination_id' => Structure::factory(),
            'patient_id' => Patient::factory(),
            'praticien_referent_id' => User::factory(),
            'motif' => fake()->sentence(),
            'statut' => 'envoye',
        ];
    }
}
