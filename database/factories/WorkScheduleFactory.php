<?php

namespace Database\Factories;

use App\Domain\Rh\Models\WorkSchedule;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkSchedule>
 */
class WorkScheduleFactory extends Factory
{
    protected $model = WorkSchedule::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'user_id' => User::factory(),
            'site_id' => Site::factory(),
            'jour_semaine' => 1,
            'date' => null,
            'heure_debut' => '08:00:00',
            'heure_fin' => '17:00:00',
            'type' => 'normal',
        ];
    }
}
