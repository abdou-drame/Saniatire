<?php

namespace App\Domain\Shared\Tenancy;

use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Validator;

/**
 * Les règles `exists:` (et Rule::exists) interrogent la base directement,
 * sans passer par les modèles Eloquent : TenantScope ne s'y applique pas.
 * Sans ce validateur, un utilisateur de la structure A pouvait référencer
 * l'id d'un patient, d'un site, d'un praticien… de la structure B (rendez-
 * vous rattaché au patient d'une autre structure, par exemple).
 *
 * Toute vérification d'existence sur une table qui porte une colonne
 * structure_id est donc restreinte à la structure de l'utilisateur
 * connecté. Les tables sans structure_id (référentiels globaux : structures,
 * rôles, codes CIM/LOINC, formules) et les requêtes sans utilisateur de
 * structure (connexion, administration plateforme) ne sont pas touchées.
 * Les règles `unique:` ne passent pas par ici : l'unicité d'un email de
 * connexion, par exemple, reste globale.
 */
class TenantAwareValidator extends Validator
{
    /** @var array<string, bool> */
    private static array $tenantTables = [];

    protected function getExistCount($connection, $table, $column, $value, $parameters)
    {
        $structureId = TenantScope::currentStructureId();

        if ($structureId === null || ! $this->isTenantTable($connection, $table)) {
            return parent::getExistCount($connection, $table, $column, $value, $parameters);
        }

        $verifier = $this->getPresenceVerifier($connection);

        $extra = $this->getExtraConditions(array_values(array_slice($parameters, 2)));

        if ($this->currentRule instanceof \Illuminate\Validation\Rules\Exists) {
            $extra = array_merge($extra, $this->currentRule->queryCallbacks());
        }

        $extra[] = fn ($query) => $query->where($table.'.structure_id', $structureId);

        return is_array($value)
            ? $verifier->getMultiCount($table, $column, $value, $extra)
            : $verifier->getCount($table, $column, $value, null, null, $extra);
    }

    private function isTenantTable(?string $connection, string $table): bool
    {
        $key = ($connection ?? '').'|'.$table;

        return self::$tenantTables[$key] ??= Schema::connection($connection)->hasColumn($table, 'structure_id');
    }
}
