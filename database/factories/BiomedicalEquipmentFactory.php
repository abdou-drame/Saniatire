<?php

namespace Database\Factories;

use App\Domain\Biomedical\Models\BiomedicalEquipment;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BiomedicalEquipment>
 */
class BiomedicalEquipmentFactory extends Factory
{
    protected $model = BiomedicalEquipment::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'site_id' => Site::factory(),
            'supplier_id' => null,
            'nom' => fake()->words(3, true),
            'categorie' => fake()->randomElement(['imagerie', 'bloc', 'laboratoire', 'reanimation']),
            'numero_serie' => strtoupper(fake()->bothify('SN-########')),
            'date_acquisition' => now()->subYears(2)->toDateString(),
            'date_fin_garantie' => now()->addYear()->toDateString(),
            'statut' => 'en_service',
        ];
    }
}
