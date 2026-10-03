<?php

namespace App\Domain\Structure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cahier des charges §7 : statut actif/inactif d'un module pour une
 * structure donnée. Géré exclusivement par l'administration plateforme
 * (App\Http\Controllers\Api\Platform\StructureModuleController) et lu
 * uniquement via App\Domain\Platform\ModuleCatalog, seule source de la
 * règle d'activation (pas de ligne = module actif). Pas de BelongsToTenant : ce n'est pas une
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
