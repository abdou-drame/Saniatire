<?php

namespace Database\Factories;

use App\Domain\Icd\Models\IcdCodeMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IcdCodeMapping>
 */
class IcdCodeMappingFactory extends Factory
{
    protected $model = IcdCodeMapping::class;

    public function definition(): array
    {
        return [
            'code_source' => strtoupper(fake()->bothify('?##')),
            'version_source' => 'CIM-10',
            'code_cible' => strtoupper(fake()->bothify('??##')),
            'version_cible' => 'CIM-11',
        ];
    }
}
