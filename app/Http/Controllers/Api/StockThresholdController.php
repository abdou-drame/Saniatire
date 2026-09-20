<?php

namespace App\Http\Controllers\Api;

use App\Domain\Pharmacie\Models\StockThreshold;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockThresholdRequest;
use App\Http\Resources\StockThresholdResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class StockThresholdController extends Controller implements HasMiddleware
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
        $thresholds = StockThreshold::query()
            ->when($request->integer('site_id'), fn ($q, $id) => $q->where('site_id', $id))
            ->when($request->integer('product_id'), fn ($q, $id) => $q->where('product_id', $id))
            ->paginate();

        return StockThresholdResource::collection($thresholds)->response();
    }

    public function store(StockThresholdRequest $request): JsonResponse
    {
        $threshold = StockThreshold::create($request->validated())->refresh();

        return (new StockThresholdResource($threshold))->response()->setStatusCode(201);
    }

    public function show(StockThreshold $stockThreshold): StockThresholdResource
    {
        return new StockThresholdResource($stockThreshold);
    }

    public function update(StockThresholdRequest $request, StockThreshold $stockThreshold): StockThresholdResource
    {
        $stockThreshold->update($request->validated());

        return new StockThresholdResource($stockThreshold);
    }

    public function destroy(StockThreshold $stockThreshold): JsonResponse
    {
        $stockThreshold->delete();

        return response()->json(null, 204);
    }
}
