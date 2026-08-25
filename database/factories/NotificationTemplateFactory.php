<?php

namespace Database\Factories;

use App\Domain\Notification\Models\NotificationTemplate;
use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationTemplate>
 */
class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'type_evenement' => 'rdv_cree',
            'canal' => 'email',
            'sujet' => 'Confirmation de rendez-vous',
            'contenu' => 'Bonjour {patient_nom}, votre rendez-vous est confirmé le {date_rdv}.',
            'actif' => true,
        ];
    }
}
