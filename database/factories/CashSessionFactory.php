<?php

namespace Database\Factories;

use App\Domain\Caisse\Models\CashSession;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashSession>
 */
class CashSessionFactory extends Factory
{
    protected $model = CashSession::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'site_id' => Site::factory(),
            'caissier_id' => User::factory(),
            'montant_ouverture' => 10000,
            'montant_cloture' => null,
            'ecart' => null,
            'ouverte_le' => now(),
            'fermee_le' => null,
            'statut' => 'ouverte',
        ];
    }
}
