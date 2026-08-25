<?php

namespace App\Http\Controllers\Api;

use App\Domain\Biomedical\Models\EquipmentMaintenance;
use App\Http\Controllers\Controller;
use App\Http\Requests\EquipmentMaintenanceRequest;
use App\Http\Resources\EquipmentMaintenanceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class EquipmentMaintenanceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:biomedical.view', only: ['index', 'show', 'upcoming', 'overdue']),
            new Middleware('permission:biomedical.maintenance', only: ['store', 'update']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $maintenances = EquipmentMaintenance::query()
            ->with(['equipment:id,nom', 'intervenant:id,first_name,last_name'])
            ->when($request->integer('biomedical_equipment_id'), fn ($q, $id) => $q->where('biomedical_equipment_id', $id))
            ->orderBy('date_prevue')
            ->paginate();

        return EquipmentMaintenanceResource::collection($maintenances)->response();
    }

    public function store(EquipmentMaintenanceRequest $request): JsonResponse
    {
        $maintenance = EquipmentMaintenance::create($request->validated())->refresh();

        return (new EquipmentMaintenanceResource($maintenance))->response()->setStatusCode(201);
    }

    public function show(EquipmentMaintenance $equipmentMaintenance): EquipmentMaintenanceResource
    {
        return new EquipmentMaintenanceResource($equipmentMaintenance->load(['equipment:id,nom', 'intervenant:id,first_name,last_name']));
    }

    public function update(EquipmentMaintenanceRequest $request, EquipmentMaintenance $equipmentMaintenance): EquipmentMaintenanceResource
    {
        $equipmentMaintenance->update($request->validated());

        return new EquipmentMaintenanceResource($equipmentMaintenance);
    }

    /** Maintenances préventives à venir, non encore réalisées. */
    public function upcoming(): JsonResponse
    {
        $maintenances = EquipmentMaintenance::query()
            ->with(['equipment:id,nom', 'intervenant:id,first_name,last_name'])
            ->where('type', 'preventive')
            ->whereNull('date_realisee')
            ->where('statut', 'planifiee')
            ->where('date_prevue', '>=', now()->toDateString())
            ->orderBy('date_prevue')
            ->get();

        return EquipmentMaintenanceResource::collection($maintenances)->response();
    }

    /** Maintenances en retard : date prévue dépassée, jamais réalisée. */
    public function overdue(): JsonResponse
    {
        $maintenances = EquipmentMaintenance::query()
            ->with(['equipment:id,nom', 'intervenant:id,first_name,last_name'])
            ->whereNull('date_realisee')
            ->where('statut', 'planifiee')
            ->where('date_prevue', '<', now()->toDateString())
            ->orderBy('date_prevue')
            ->get();

        return EquipmentMaintenanceResource::collection($maintenances)->response();
    }
}
