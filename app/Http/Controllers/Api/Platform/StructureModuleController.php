<?php

namespace App\Http\Controllers\Api\Platform;

use App\Domain\Platform\ModuleCatalog;
use App\Domain\Structure\Models\Structure;
use App\Domain\Structure\Models\StructureModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\StructureModuleUpdateRequest;
use Illuminate\Http\JsonResponse;

/**
 * Livraison B : modules réellement actifs (middleware `module:<clé>`).
 * L'écran plateforme reçoit le catalogue complet (ModuleCatalog) et non les
 * seules lignes structure_modules : un module premium sans ligne est actif,
 * un module socle est toujours actif et ne peut pas être désactivé.
 */
class StructureModuleController extends Controller
{
    public function index(Structure $structure): JsonResponse
    {
        $rows = $structure->modules()->get()->keyBy('module');

        $core = collect(ModuleCatalog::CORE)->map(fn (string $label, string $module) => [
            'module' => $module,
            'label' => $label,
            'is_core' => true,
            'is_active' => true,
            'activated_at' => null,
            'deactivated_at' => null,
        ]);

        $premium = collect(ModuleCatalog::PREMIUM)->map(fn (string $label, string $module) => [
            'module' => $module,
            'label' => $label,
            'is_core' => false,
            'is_active' => $rows->get($module)?->is_active ?? true,
            'activated_at' => $rows->get($module)?->activated_at,
            'deactivated_at' => $rows->get($module)?->deactivated_at,
        ]);

        return response()->json(['data' => $core->merge($premium)->values()]);
    }

    public function update(StructureModuleUpdateRequest $request, Structure $structure, string $module): JsonResponse
    {
        abort_if($structure->trashed(), 409, 'Cette structure est archivée : elle reste consultable mais ne peut plus être modifiée.');

        if (ModuleCatalog::isCore($module)) {
            abort(422, 'Le module « '.ModuleCatalog::label($module).' » fait partie du socle : il est toujours actif et ne peut pas être désactivé.');
        }

        abort_unless(ModuleCatalog::isPremium($module), 404, 'Module inconnu.');

        $isActive = $request->validated('is_active');
        $row = StructureModule::firstOrNew(['structure_id' => $structure->id, 'module' => $module]);
        $wasActive = $row->exists ? $row->is_active : true;

        $row->fill([
            'is_active' => $isActive,
            'activated_at' => $isActive && ! $wasActive ? now() : ($row->activated_at ?? ($isActive ? now() : null)),
            'deactivated_at' => $isActive ? null : now(),
        ])->save();

        activity('administration_plateforme')
            ->causedBy($request->user('platform'))
            ->performedOn($row)
            ->withProperties([
                'action' => $isActive ? 'activation_module' : 'desactivation_module',
                'module' => $module,
                'hors_isolation' => true,
            ])
            ->tap(function ($activity) use ($structure) {
                $activity->structure_id = $structure->id;
            })
            ->log("Action de l'administrateur de plateforme sur le module {$module}, hors du cadre normal d'isolation par structure.");

        return response()->json(['data' => [
            'module' => $module,
            'label' => ModuleCatalog::label($module),
            'is_core' => false,
            'is_active' => $row->is_active,
            'activated_at' => $row->activated_at,
            'deactivated_at' => $row->deactivated_at,
        ]]);
    }
}
