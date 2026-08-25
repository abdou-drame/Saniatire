<?php

namespace App\Http\Controllers\Api;

use App\Domain\Hospitalisation\Models\Ward;
use App\Http\Controllers\Controller;
use App\Http\Requests\WardRequest;
use App\Http\Resources\WardResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class WardController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:hospitalisation.view', only: ['index', 'show', 'occupancyStats']),
            new Middleware('permission:hospitalisation.create', only: ['store']),
            new Middleware('permission:hospitalisation.update', only: ['update']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $wards = Ward::query()
            ->when($request->integer('site_id'), fn ($q, $id) => $q->where('site_id', $id))
            ->paginate();

        return WardResource::collection($wards)->response();
    }

    public function store(WardRequest $request): JsonResponse
    {
        $ward = Ward::create($request->validated())->refresh();

        return (new WardResource($ward))->response()->setStatusCode(201);
    }

    public function show(Ward $ward): WardResource
    {
        return new WardResource($ward->load('beds'));
    }

    public function update(WardRequest $request, Ward $ward): WardResource
    {
        $ward->update($request->validated());

        return new WardResource($ward);
    }

    /**
     * Dedicated endpoint (cahier des charges §3) : taux d'occupation par
     * service, filtrable par site.
     */
    public function occupancyStats(Request $request): JsonResponse
    {
        $wards = Ward::query()
            ->when($request->integer('site_id'), fn ($q, $id) => $q->where('site_id', $id))
            ->withCount([
                'beds',
                'beds as occupied_beds_count' => fn ($q) => $q->where('status', 'occupe'),
            ])
            ->get();

        $stats = $wards->map(fn (Ward $ward) => [
            'ward_id' => $ward->id,
            'ward_name' => $ward->name,
            'site_id' => $ward->site_id,
            'total_beds' => $ward->beds_count,
            'occupied_beds' => $ward->occupied_beds_count,
            'occupancy_rate' => $ward->beds_count > 0
                ? round($ward->occupied_beds_count / $ward->beds_count * 100, 1)
                : 0.0,
        ]);

        return response()->json(['data' => $stats->values()]);
    }
}
