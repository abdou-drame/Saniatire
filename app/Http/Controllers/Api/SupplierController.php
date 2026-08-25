<?php

namespace App\Http\Controllers\Api;

use App\Domain\Achats\Models\Supplier;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierRequest;
use App\Http\Resources\SupplierResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SupplierController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:achats.view', only: ['index', 'show']),
            new Middleware('permission:achats.create', only: ['store']),
            new Middleware('permission:achats.update', only: ['update']),
            new Middleware('permission:achats.delete', only: ['destroy']),
        ];
    }

    public function index(): JsonResponse
    {
        return SupplierResource::collection(Supplier::query()->orderBy('nom')->paginate())->response();
    }

    public function store(SupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::create($request->validated())->refresh();

        return (new SupplierResource($supplier))->response()->setStatusCode(201);
    }

    public function show(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($supplier);
    }

    public function update(SupplierRequest $request, Supplier $supplier): SupplierResource
    {
        $supplier->update($request->validated());

        return new SupplierResource($supplier);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        return response()->json(null, 204);
    }
}
