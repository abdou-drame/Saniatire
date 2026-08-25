<?php

namespace Database\Factories;

use App\Domain\Achats\Models\ApprovalRule;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalRule>
 */
class ApprovalRuleFactory extends Factory
{
    protected $model = ApprovalRule::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'level' => 1,
            'min_amount' => 0,
            'role_name' => 'gestionnaire_stock',
        ];
    }
}
