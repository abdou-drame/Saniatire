<?php

namespace App\Domain\Notification\Models;

use App\Domain\Shared\Tenancy\BelongsToTenant;
use Database\Factories\NotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationPreference extends Model
{
    /** @use HasFactory<NotificationPreferenceFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'structure_id',
        'notifiable_type',
        'notifiable_id',
        'canaux',
    ];

    protected function casts(): array
    {
        return [
            'canaux' => 'array',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
