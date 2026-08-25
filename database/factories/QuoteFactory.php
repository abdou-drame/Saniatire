<?php

namespace Database\Factories;

use App\Domain\Facturation\Models\Quote;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'site_id' => Site::factory(),
            'patient_id' => Patient::factory(),
            'insurance_convention_id' => null,
            'converted_invoice_id' => null,
            'date_emission' => now()->toDateString(),
            'montant_total' => 0,
            'statut' => 'brouillon',
        ];
    }
}
