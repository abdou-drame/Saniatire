<?php

namespace Database\Factories;

use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Structure>
 */
class StructureFactory extends Factory
{
    protected $model = Structure::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'code' => strtoupper(fake()->unique()->bothify('STR-###??')),
            'legal_name' => $name,
            'trade_name' => $name,
            'type' => fake()->randomElement([
                'cabinet', 'centre_specialise', 'laboratoire', 'imagerie', 'clinique', 'polyclinique', 'groupe_sante',
            ]),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => 'Côte d\'Ivoire',
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->companyEmail(),
            'registration_number' => fake()->bothify('RC-####??'),
            'tax_number' => fake()->bothify('CC-#######'),
            'currency' => 'XOF',
            'locale' => 'fr',
            'is_active' => true,
        ];
    }
}
