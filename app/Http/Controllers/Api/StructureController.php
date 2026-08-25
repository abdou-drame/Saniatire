<?php

namespace App\Http\Controllers\Api;

use App\Domain\Structure\Models\Structure;
use App\Http\Controllers\Controller;
use App\Http\Requests\StructureRequest;
use App\Http\Resources\StructureResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Unlike Site/User/Patient, Structure has no structure_id column — it IS
 * the tenant boundary, so it can't use the BelongsToTenant/TenantScope
 * mechanism. Instead, every action here explicitly restricts to the
 * caller's own structure (404 on mismatch, same as a scoped-out record),
 * except store() which onboards a brand-new structure and therefore has
 * no existing tenant to check against.
 */
class StructureController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:structures.view', only: ['index', 'show']),
            new Middleware('permission:structures.create', only: ['store']),
            new Middleware('permission:structures.update', only: ['update']),
            new Middleware('permission:structures.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): StructureResource
    {
        return new StructureResource($request->user()->structure);
    }

    public function store(StructureRequest $request): JsonResponse
    {
        $structure = Structure::create($request->validated());

        return (new StructureResource($structure))->response()->setStatusCode(201);
    }

    public function show(Request $request, Structure $structure): StructureResource
    {
        $this->authorizeOwnStructure($request, $structure);

        return new StructureResource($structure);
    }

    public function update(StructureRequest $request, Structure $structure): StructureResource
    {
        $this->authorizeOwnStructure($request, $structure);

        $structure->update($request->validated());

        return new StructureResource($structure);
    }

    public function destroy(Request $request, Structure $structure): JsonResponse
    {
        $this->authorizeOwnStructure($request, $structure);

        $structure->delete();

        return response()->json(null, 204);
    }

    private function authorizeOwnStructure(Request $request, Structure $structure): void
    {
        abort_unless($structure->id === $request->user()->structure_id, 404);
    }
}
