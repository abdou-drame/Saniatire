<?php

namespace Database\Factories;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Facturation\Models\BillableItem;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillableItem>
 */
class BillableItemFactory extends Factory
{
    protected $model = BillableItem::class;

    public function definition(): array
    {
        $prixUnitaire = $this->faker->randomFloat(2, 1000, 50000);

        return [
            'structure_id' => Structure::factory(),
            'patient_id' => Patient::factory(),
            'billable_type' => Consultation::class,
            'billable_id' => $this->faker->numberBetween(1, 999999),
            'categorie' => 'consultation',
            'code_prestation' => 'CONSULTATION_GENERALE',
            'libelle' => 'Consultation',
            'quantite' => 1,
            'prix_unitaire' => $prixUnitaire,
            'montant_total' => $prixUnitaire,
            'statut' => 'a_facturer',
        ];
    }
}
