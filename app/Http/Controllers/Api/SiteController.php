<?php

namespace App\Http\Controllers\Api;

use App\Domain\Structure\Models\Site;
use App\Http\Controllers\Controller;
use App\Http\Requests\SiteRequest;
use App\Http\Resources\SiteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SiteController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:sites.view', only: ['index', 'show']),
            new Middleware('permission:sites.create', only: ['store']),
            new Middleware('permission:sites.update', only: ['update']),
            new Middleware('permission:sites.delete', only: ['destroy']),
        ];
    }

    public function index(): JsonResponse
    {
        return SiteResource::collection(Site::query()->paginate())->response();
    }

    public function store(SiteRequest $request): JsonResponse
    {
        $site = Site::create($request->validated());

        return (new SiteResource($site))->response()->setStatusCode(201);
    }

    public function show(Site $site): SiteResource
    {
        return new SiteResource($site);
    }

    public function update(SiteRequest $request, Site $site): SiteResource
    {
        $site->update($request->validated());

        return new SiteResource($site);
    }

    public function destroy(Site $site): JsonResponse
    {
        $site->delete();

        return response()->json(null, 204);
    }
}
