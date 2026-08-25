<?php

namespace App\Http\Controllers\Api;

use App\Domain\Biomedical\Models\BiomedicalEquipment;
use App\Http\Controllers\Controller;
use App\Http\Requests\BiomedicalEquipmentRequest;
use App\Http\Resources\BiomedicalEquipmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BiomedicalEquipmentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:biomedical.view', only: ['index', 'show']),
            new Middleware('permission:biomedical.create', only: ['store']),
            new Middleware('permission:biomedical.update', only: ['update']),
            new Middleware('permission:biomedical.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $equipment = BiomedicalEquipment::query()
            ->with(['site:id,name', 'supplier:id,nom'])
            ->when($request->integer('site_id'), fn ($q, $id) => $q->where('site_id', $id))
            ->when($request->string('statut')->isNotEmpty(), fn ($q) => $q->where('statut', $request->string('statut')))
            ->orderBy('nom')
            ->paginate();

        return BiomedicalEquipmentResource::collection($equipment)->response();
    }

    public function store(BiomedicalEquipmentRequest $request): JsonResponse
    {
        $equipment = BiomedicalEquipment::create($request->validated())->refresh();

        return (new BiomedicalEquipmentResource($equipment))->response()->setStatusCode(201);
    }

    public function show(BiomedicalEquipment $biomedicalEquipment): BiomedicalEquipmentResource
    {
        return new BiomedicalEquipmentResource($biomedicalEquipment->load(['maintenances', 'site:id,name', 'supplier:id,nom']));
    }

    public function update(BiomedicalEquipmentRequest $request, BiomedicalEquipment $biomedicalEquipment): BiomedicalEquipmentResource
    {
        $biomedicalEquipment->update($request->validated());

        return new BiomedicalEquipmentResource($biomedicalEquipment);
    }

    public function destroy(BiomedicalEquipment $biomedicalEquipment): JsonResponse
    {
        $biomedicalEquipment->delete();

        return response()->json(null, 204);
    }
}
