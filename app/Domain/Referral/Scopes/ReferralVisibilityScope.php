<?php

namespace App\Domain\Referral\Scopes;

use App\Domain\Shared\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Étape 7b §5 : exception délibérément étroite et non générique à
 * l'isolation stricte par tenant — appliquée uniquement à PatientReferral
 * (jamais TenantScope lui-même), et seulement pour rendre visible un
 * référencement aux deux structures qui y sont explicitement parties
 * (celle d'origine ET celle de destination), jamais à une troisième.
 */
class ReferralVisibilityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $structureId = TenantScope::currentStructureId();

        if (! $structureId) {
            return;
        }

        $builder->where(function (Builder $q) use ($structureId) {
            $q->where('structure_origine_id', $structureId)
                ->orWhere('structure_destination_id', $structureId);
        });
    }
}
