<?php

namespace Database\Factories;

use App\Domain\Notification\Models\Notification;
use App\Domain\Patient\Models\Patient;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'notifiable_type' => Patient::class,
            'notifiable_id' => Patient::factory(),
            'type_evenement' => 'rdv_cree',
            'canal' => 'email',
            'sujet_final' => 'Confirmation de rendez-vous',
            'contenu_final' => 'Votre rendez-vous est confirmé.',
            'destinataire' => fake()->safeEmail(),
            'statut' => 'en_attente',
            'scheduled_for' => null,
            'envoye_at' => null,
            'erreur' => null,
        ];
    }
}
