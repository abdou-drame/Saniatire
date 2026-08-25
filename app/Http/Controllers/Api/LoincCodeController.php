<?php

namespace App\Http\Controllers\Api;

use App\Domain\Laboratoire\Models\LoincCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\LoincCodeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Read-only, same doctrine as IcdCodeController: the LOINC referentiel is
 * shared across tenants and only ever mutated by LoincCodeSeeder.
 */
class LoincCodeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:laboratoire.view', only: ['index', 'show']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $codes = LoincCode::query()
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request) {
                $term = '%'.mb_strtolower($request->string('search')->toString()).'%';
                $q->where(fn ($q) => $q->whereRaw('LOWER(code) LIKE ?', [$term])->orWhereRaw('LOWER(label) LIKE ?', [$term]));
            })
            ->orderBy('code')
            ->paginate();

        return LoincCodeResource::collection($codes)->response();
    }

    public function show(LoincCode $loincCode): LoincCodeResource
    {
        return new LoincCodeResource($loincCode);
    }
}
