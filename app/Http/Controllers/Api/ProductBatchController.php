<?php

namespace App\Http\Controllers\Api;

use App\Domain\Pharmacie\Models\ProductBatch;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductBatchRequest;
use App\Http\Resources\ProductBatchResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProductBatchController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:stock.view', only: ['index', 'show']),
            new Middleware('permission:stock.create', only: ['store']),
            new Middleware('permission:stock.update', only: ['update']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $batches = ProductBatch::query()
            ->with(['product:id,nom_commercial,dci,unite_vente', 'site:id,name', 'supplier:id,nom'])
            ->when($request->integer('product_id'), fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->integer('site_id'), fn ($q, $id) => $q->where('site_id', $id))
            ->orderBy('date_peremption')
            ->paginate();

        return ProductBatchResource::collection($batches)->response();
    }

    public function store(ProductBatchRequest $request): JsonResponse
    {
        $batch = ProductBatch::create($request->validated())->refresh();

        return (new ProductBatchResource($batch))->response()->setStatusCode(201);
    }

    public function show(ProductBatch $productBatch): ProductBatchResource
    {
        return new ProductBatchResource($productBatch->load(['product:id,nom_commercial,dci,unite_vente', 'site:id,name', 'supplier:id,nom']));
    }

    public function update(ProductBatchRequest $request, ProductBatch $productBatch): ProductBatchResource
    {
        $productBatch->update($request->validated());

        return new ProductBatchResource($productBatch);
    }
}
