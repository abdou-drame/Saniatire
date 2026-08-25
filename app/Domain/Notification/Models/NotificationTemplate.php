<?php

namespace App\Domain\Notification\Models;

use App\Domain\Shared\Tenancy\BelongsToTenant;
use Database\Factories\NotificationTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class NotificationTemplate extends Model
{
    /** @use HasFactory<NotificationTemplateFactory> */
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'structure_id',
        'type_evenement',
        'canal',
        'sujet',
        'contenu',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    /**
     * Rend le contenu en substituant {variable} par sa valeur. Les
     * variables absentes du tableau sont laissées telles quelles plutôt
     * que remplacées par une chaîne vide, pour rester visibles en cas
     * d'erreur de configuration d'un template.
     */
    public function render(array $variables): array
    {
        $substitute = fn (?string $text) => $text === null ? null : preg_replace_callback(
            '/\{(\w+)\}/',
            fn ($m) => array_key_exists($m[1], $variables) ? (string) $variables[$m[1]] : $m[0],
            $text
        );

        return [
            'sujet' => $substitute($this->sujet),
            'contenu' => $substitute($this->contenu),
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('notification_template');
    }
}
