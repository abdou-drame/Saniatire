<?php

namespace Database\Factories;

use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreference::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'notifiable_type' => Patient::class,
            'notifiable_id' => Patient::factory(),
            'canaux' => ['email'],
        ];
    }
}
