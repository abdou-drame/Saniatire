<?php

namespace Database\Factories;

use App\Domain\Caisse\Models\Payment;
use App\Domain\Facturation\Models\Invoice;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'site_id' => Site::factory(),
            'invoice_id' => Invoice::factory(),
            'cash_session_id' => null,
            'caissier_id' => User::factory(),
            'mode_paiement' => 'carte',
            'reference_transaction' => null,
            'statut_mobile_money' => null,
            'montant' => 1000,
            'paid_at' => now(),
        ];
    }
}
