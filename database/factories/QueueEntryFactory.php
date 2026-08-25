<?php

namespace Database\Factories;

use App\Domain\Patient\Models\Patient;
use App\Domain\Queue\Models\QueueEntry;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueueEntry>
 */
class QueueEntryFactory extends Factory
{
    protected $model = QueueEntry::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'site_id' => Site::factory(),
            'patient_id' => Patient::factory(),
            'service' => fake()->randomElement(['Consultation générale', 'Pédiatrie', 'Gynécologie']),
            'priority' => 'normale',
            'status' => 'en_attente',
            'arrived_at' => now(),
        ];
    }
}
