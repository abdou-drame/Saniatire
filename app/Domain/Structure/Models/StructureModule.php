<?php

namespace App\Domain\Structure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cahier des charges §7 : statut actif/inactif d'un module pour une
 * structure donnée. Géré exclusivement par l'administration plateforme
 * (App\Http\Controllers\Api\Platform\StructureModuleController) — aucune
 * lecture n'est branchée ailleurs dans l'application à ce stade, c'est
 * volontaire (voir le prompt d'origine : la donnée et l'écran seulement,
 * pas l'activation réelle). Pas de BelongsToTenant : ce n'est pas une
 * donnée d'une structure gérée par elle-même, mais une donnée sur une
 * structure gérée par la plateforme.
 */
class StructureModule extends Model
{
    protected $fillable = [
        'structure_id',
        'module',
        'is_active',
        'activated_at',
        'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }
}
