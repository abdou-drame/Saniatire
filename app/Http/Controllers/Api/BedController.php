<?php

namespace App\Http\Controllers\Api;

use App\Domain\Hospitalisation\Models\Bed;
use App\Http\Controllers\Controller;
use App\Http\Requests\BedRequest;
use App\Http\Resources\BedResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BedController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:hospitalisation.view', only: ['index', 'show']),
            new Middleware('permission:hospitalisation.create', only: ['store']),
            new Middleware('permission:hospitalisation.update', only: ['update']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $beds = Bed::query()
            ->when($request->integer('ward_id'), fn ($q, $id) => $q->where('ward_id', $id))
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->paginate();

        return BedResource::collection($beds)->response();
    }

    public function store(BedRequest $request): JsonResponse
    {
        $bed = Bed::create($request->validated())->refresh();

        return (new BedResource($bed))->response()->setStatusCode(201);
    }

    public function show(Bed $bed): BedResource
    {
        return new BedResource($bed);
    }

    public function update(BedRequest $request, Bed $bed): BedResource
    {
        $bed->update($request->validated());

        return new BedResource($bed);
    }
}
