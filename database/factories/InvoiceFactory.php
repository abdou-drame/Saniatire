<?php

namespace Database\Factories;

use App\Domain\Facturation\Models\Invoice;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'site_id' => Site::factory(),
            'patient_id' => Patient::factory(),
            'insurance_convention_id' => null,
            'date_emission' => now()->toDateString(),
            'montant_total' => 0,
            'montant_part_patient' => 0,
            'montant_part_assurance' => 0,
            'statut' => 'brouillon',
        ];
    }
}
