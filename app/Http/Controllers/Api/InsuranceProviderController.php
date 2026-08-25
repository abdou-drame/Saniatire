<?php

namespace App\Http\Controllers\Api;

use App\Domain\Assurance\Models\InsuranceProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\InsuranceProviderRequest;
use App\Http\Resources\InsuranceProviderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class InsuranceProviderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:assurance.view', only: ['index', 'show']),
            new Middleware('permission:assurance.create', only: ['store']),
            new Middleware('permission:assurance.update', only: ['update']),
            new Middleware('permission:assurance.delete', only: ['destroy']),
        ];
    }

    public function index(): JsonResponse
    {
        return InsuranceProviderResource::collection(InsuranceProvider::query()->orderBy('nom')->paginate())->response();
    }

    public function store(InsuranceProviderRequest $request): JsonResponse
    {
        $provider = InsuranceProvider::create($request->validated())->refresh();

        return (new InsuranceProviderResource($provider))->response()->setStatusCode(201);
    }

    public function show(InsuranceProvider $insuranceProvider): InsuranceProviderResource
    {
        return new InsuranceProviderResource($insuranceProvider->load('conventions'));
    }

    public function update(InsuranceProviderRequest $request, InsuranceProvider $insuranceProvider): InsuranceProviderResource
    {
        $insuranceProvider->update($request->validated());

        return new InsuranceProviderResource($insuranceProvider);
    }

    public function destroy(InsuranceProvider $insuranceProvider): JsonResponse
    {
        $insuranceProvider->delete();

        return response()->json(null, 204);
    }
}
