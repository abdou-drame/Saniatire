<?php

namespace Database\Factories;

use App\Domain\Rh\Models\LeaveRequest;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'user_id' => User::factory(),
            'type' => 'conge_annuel',
            'date_debut' => now()->addWeek()->toDateString(),
            'date_fin' => now()->addWeek()->addDays(4)->toDateString(),
            'statut' => 'demande',
            'validated_by' => null,
            'commentaire' => null,
        ];
    }
}
