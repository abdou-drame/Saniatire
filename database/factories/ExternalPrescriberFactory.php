<?php

namespace Database\Factories;

use App\Domain\Prescripteur\Models\ExternalPrescriber;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<ExternalPrescriber>
 */
class ExternalPrescriberFactory extends Factory
{
    protected $model = ExternalPrescriber::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'nom' => 'Dr '.fake()->lastName(),
            'specialite' => fake()->randomElement(['Cardiologie', 'Pédiatrie', 'Médecine générale', 'Gynécologie']),
            'email' => fake()->unique()->safeEmail(),
            'telephone' => fake()->phoneNumber(),
            'statut' => 'actif',
        ];
    }

    public function withPortalActivated(): static
    {
        return $this->state(fn () => [
            'password' => Hash::make('password'),
            'portal_activated_at' => now(),
        ]);
    }

    public function inactif(): static
    {
        return $this->state(fn () => ['statut' => 'inactif']);
    }
}
