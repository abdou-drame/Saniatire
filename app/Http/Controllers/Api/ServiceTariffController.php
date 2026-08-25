<?php

namespace App\Http\Controllers\Api;

use App\Domain\Facturation\Models\ServiceTariff;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceTariffRequest;
use App\Http\Resources\ServiceTariffResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ServiceTariffController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:facturation.view', only: ['index', 'show']),
            new Middleware('permission:facturation.create', only: ['store']),
            new Middleware('permission:facturation.update', only: ['update']),
            new Middleware('permission:facturation.delete', only: ['destroy']),
        ];
    }

    public function index(): JsonResponse
    {
        return ServiceTariffResource::collection(ServiceTariff::query()->orderBy('categorie')->orderBy('code')->paginate())->response();
    }

    public function store(ServiceTariffRequest $request): JsonResponse
    {
        $tariff = ServiceTariff::create($request->validated())->refresh();

        return (new ServiceTariffResource($tariff))->response()->setStatusCode(201);
    }

    public function show(ServiceTariff $serviceTariff): ServiceTariffResource
    {
        return new ServiceTariffResource($serviceTariff);
    }

    public function update(ServiceTariffRequest $request, ServiceTariff $serviceTariff): ServiceTariffResource
    {
        $serviceTariff->update($request->validated());

        return new ServiceTariffResource($serviceTariff);
    }

    public function destroy(ServiceTariff $serviceTariff): JsonResponse
    {
        $serviceTariff->delete();

        return response()->json(null, 204);
    }
}
