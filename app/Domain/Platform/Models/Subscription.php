<?php

namespace App\Domain\Platform\Models;

use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une période d'abonnement d'une structure. Gérée exclusivement par
 * l'administration plateforme : une nouvelle période = une nouvelle ligne,
 * une période existante n'est jamais réécrite (seul son statut peut passer
 * de active/essai à suspendue et inversement). Pas de BelongsToTenant,
 * même raison que StructureModule : donnée sur une structure, pas d'une
 * structure.
 */
class Subscription extends Model
{
    public const STATUSES = ['essai', 'active', 'suspendue', 'resiliee'];

    protected $fillable = [
        'structure_id',
        'plan_id',
        'starts_at',
        'ends_at',
        'status',
        'billing_period',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class)->withTrashed();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'created_by');
    }
}
