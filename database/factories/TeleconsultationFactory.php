<?php

namespace Database\Factories;

use App\Domain\Appointment\Models\Appointment;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Structure;
use App\Domain\Teleconsultation\Models\Teleconsultation;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Teleconsultation>
 */
class TeleconsultationFactory extends Factory
{
    protected $model = Teleconsultation::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'appointment_id' => Appointment::factory(),
            'patient_id' => Patient::factory(),
            'practitioner_id' => User::factory(),
            'statut' => 'planifiee',
        ];
    }
}
