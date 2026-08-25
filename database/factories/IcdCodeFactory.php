<?php

namespace Database\Factories;

use App\Domain\Icd\Models\IcdCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IcdCode>
 */
class IcdCodeFactory extends Factory
{
    protected $model = IcdCode::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->bothify('?##')),
            'version' => 'CIM-10',
            'label' => fake()->sentence(3),
            'level' => 'code',
            'status' => 'actif',
        ];
    }
}
