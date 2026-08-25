<?php

namespace Database\Factories;

use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sex = fake()->randomElement(['M', 'F']);

        return [
            'structure_id' => Structure::factory(),
            'first_name' => $sex === 'M' ? fake()->firstNameMale() : fake()->firstNameFemale(),
            'last_name' => fake()->lastName(),
            'sex' => $sex,
            'birth_date' => fake()->dateTimeBetween('-90 years', '-1 years')->format('Y-m-d'),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'address' => fake()->streetAddress(),
            'profession' => fake()->jobTitle(),
            'nationality' => 'Ivoirienne',
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => fake()->phoneNumber(),
            'emergency_contact_relationship' => fake()->randomElement(['Conjoint', 'Parent', 'Enfant', 'Ami']),
        ];
    }

    /**
     * Portail patient activé, mot de passe connu ("password") pour les
     * tests d'authentification.
     */
    public function withPortalActivated(): static
    {
        return $this->state(fn () => [
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'portal_activated_at' => now(),
        ]);
    }
}
