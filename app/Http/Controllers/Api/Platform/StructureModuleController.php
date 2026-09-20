<?php

namespace App\Http\Controllers\Api\Platform;

use App\Domain\Structure\Models\Structure;
use App\Domain\Structure\Models\StructureModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\StructureModuleUpdateRequest;
use App\Http\Resources\StructureModuleResource;
use Illuminate\Http\JsonResponse;

/**
 * Cahier des charges §7 : uniquement la donnée et l'écran de gestion des
 * modules activés par structure (voir StructureModule) — aucune activation
 * réelle n'est branchée ailleurs dans l'application à ce stade, volontaire.
 */
class StructureModuleController extends Controller
{
    public function index(Structure $structure): JsonResponse
    {
        $modules = $structure->modules()->orderBy('module')->get();

        return StructureModuleResource::collection($modules)->response();
    }

    public function update(StructureModuleUpdateRequest $request, Structure $structure, StructureModule $module): StructureModuleResource
    {
        abort_unless($module->structure_id === $structure->id, 404);

        $isActive = $request->validated('is_active');

        $module->update([
            'is_active' => $isActive,
            'activated_at' => $isActive ? now() : $module->activated_at,
            'deactivated_at' => $isActive ? null : now(),
        ]);

        activity('administration_plateforme')
            ->causedBy($request->user('platform'))
            ->performedOn($module)
            ->withProperties([
                'action' => $isActive ? 'activation_module' : 'desactivation_module',
                'module' => $module->module,
                'hors_isolation' => true,
            ])
            ->tap(function ($activity) use ($structure) {
                $activity->structure_id = $structure->id;
            })
            ->log("Action de l'administrateur de plateforme sur le module {$module->module}, hors du cadre normal d'isolation par structure.");

        return new StructureModuleResource($module);
    }
}
