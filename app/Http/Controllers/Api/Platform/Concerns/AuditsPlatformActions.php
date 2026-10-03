<?php

namespace App\Http\Controllers\Api\Platform\Concerns;

use Illuminate\Http\Request;

/**
 * log_name distinct 'administration_plateforme', même patron que
 * 'partage_inter_structure' pour le référencement inter-structures :
 * chaque franchissement volontaire de l'isolation normale a sa propre
 * trace explicite. structure_id forcé à la structure concernée par
 * l'action (pas celle de l'acteur, qui n'en a pas) — sans ce tap(),
 * Activity::creating ne renseignerait rien, TenantScope::currentStructureId()
 * ne reconnaissant pas le guard platform. Null pour une action qui ne
 * concerne aucune structure (ex. création d'une formule).
 */
trait AuditsPlatformActions
{
    private function auditPlatformAction(Request $request, $subject, ?int $structureId, string $action, array $properties = []): void
    {
        activity('administration_plateforme')
            ->causedBy($request->user('platform'))
            ->performedOn($subject)
            ->withProperties([
                ...$properties,
                'action' => $action,
                'hors_isolation' => true,
            ])
            ->tap(function ($activity) use ($structureId) {
                $activity->structure_id = $structureId;
            })
            ->log("Action de l'administrateur de plateforme ({$action}), hors du cadre normal d'isolation par structure.");
    }
}
