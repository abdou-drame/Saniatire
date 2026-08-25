<?php

namespace Database\Factories;

use App\Domain\Biomedical\Models\BiomedicalEquipment;
use App\Domain\Biomedical\Models\EquipmentMaintenance;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentMaintenance>
 */
class EquipmentMaintenanceFactory extends Factory
{
    protected $model = EquipmentMaintenance::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'biomedical_equipment_id' => BiomedicalEquipment::factory(),
            'intervenant_user_id' => null,
            'type' => 'preventive',
            'date_prevue' => now()->addMonth()->toDateString(),
            'date_realisee' => null,
            'intervenant_externe' => null,
            'cout' => null,
            'description' => fake()->sentence(),
            'statut' => 'planifiee',
        ];
    }
}
