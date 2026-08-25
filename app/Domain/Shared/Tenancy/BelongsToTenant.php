<?php

namespace App\Domain\Shared\Tenancy;

use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Marks a model as belonging to a single structure (tenant).
 *
 * - Every query is automatically scoped to the authenticated user's
 *   structure via TenantScope, so cross-tenant reads are impossible
 *   by default (an id from another structure resolves to "not found",
 *   not "forbidden" — this avoids leaking existence via enumeration).
 * - New records silently inherit structure_id from the authenticated
 *   user unless it was explicitly set (e.g. by a seeder run outside
 *   an HTTP request).
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (! $model->structure_id && $structureId = TenantScope::currentStructureId()) {
                $model->structure_id = $structureId;
            }
        });
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }
}
