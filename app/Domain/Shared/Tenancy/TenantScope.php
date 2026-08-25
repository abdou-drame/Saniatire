<?php

namespace App\Domain\Shared\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Filters every query on a tenant-scoped model down to the structure of the
 * currently authenticated user. Applied automatically wherever a model uses
 * the BelongsToTenant trait, so a forgotten "where" in a controller or a
 * queued job can never leak another structure's rows.
 */
class TenantScope implements Scope
{
    private static bool $resolvingAuthUser = false;

    public function apply(Builder $builder, Model $model): void
    {
        if ($structureId = static::currentStructureId()) {
            $builder->where($model->qualifyColumn('structure_id'), $structureId);
        }
    }

    /**
     * Guarded against re-entrancy: resolving the authenticated User model
     * (e.g. Sanctum looking up the token's tokenable) runs a query against
     * User itself, which carries this very scope. Without the guard, that
     * query would call Auth::user() to find out how to scope itself, which
     * triggers the same not-yet-cached auth resolution again — infinite
     * recursion. While resolution is already in flight, the lookup that
     * discovers *who* the user is must run unscoped; every other query
     * still gets properly scoped once resolution has completed.
     */
    public static function currentStructureId(): ?int
    {
        if (self::$resolvingAuthUser) {
            return null;
        }

        self::$resolvingAuthUser = true;

        try {
            $authenticatable = Auth::guard('sanctum')->user()
                ?? Auth::guard('patient')->user()
                ?? Auth::guard('prescriber')->user();

            return $authenticatable?->structure_id;
        } finally {
            self::$resolvingAuthUser = false;
        }
    }
}
