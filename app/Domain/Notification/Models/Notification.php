<?php

namespace App\Domain\Notification\Models;

use App\Domain\Shared\Tenancy\BelongsToTenant;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'notification_logs';

    protected $fillable = [
        'structure_id',
        'notifiable_type',
        'notifiable_id',
        'type_evenement',
        'canal',
        'sujet_final',
        'contenu_final',
        'destinataire',
        'statut',
        'scheduled_for',
        'envoye_at',
        'erreur',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'envoye_at' => 'datetime',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isDue(): bool
    {
        return $this->statut === 'en_attente' && ($this->scheduled_for === null || $this->scheduled_for->lte(now()));
    }
}
