<?php

namespace App\Http\Controllers\Api;

use App\Domain\Pharmacie\Models\Product;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProductController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:stock.view', only: ['index', 'show']),
            new Middleware('permission:stock.create', only: ['store']),
            new Middleware('permission:stock.update', only: ['update']),
            new Middleware('permission:stock.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->withSum('batches as stock_total', 'quantite_stock')
            ->when($request->string('categorie')->isNotEmpty(), fn ($q) => $q->where('categorie', $request->string('categorie')))
            ->when($request->has('actif'), fn ($q) => $q->where('actif', $request->boolean('actif')))
            ->orderBy('nom_commercial')
            ->paginate();

        return ProductResource::collection($products)->response();
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated())->refresh();

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->loadSum('batches as stock_total', 'quantite_stock'));
    }

    public function update(ProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(null, 204);
    }
}
