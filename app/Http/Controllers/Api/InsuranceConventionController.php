<?php

namespace App\Http\Controllers\Api;

use App\Domain\Assurance\Models\InsuranceConvention;
use App\Http\Controllers\Controller;
use App\Http\Requests\InsuranceConventionCoverageRuleRequest;
use App\Http\Requests\InsuranceConventionRequest;
use App\Http\Resources\InsuranceConventionCoverageRuleResource;
use App\Http\Resources\InsuranceConventionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class InsuranceConventionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:assurance.view', only: ['index', 'show']),
            new Middleware('permission:assurance.create', only: ['store', 'storeCoverageRule']),
            new Middleware('permission:assurance.update', only: ['update']),
            new Middleware('permission:assurance.delete', only: ['destroy']),
        ];
    }

    public function index(): JsonResponse
    {
        return InsuranceConventionResource::collection(
            InsuranceConvention::query()->with('coverageRules')->orderBy('nom')->paginate()
        )->response();
    }

    public function store(InsuranceConventionRequest $request): JsonResponse
    {
        $convention = InsuranceConvention::create($request->validated())->refresh();

        return (new InsuranceConventionResource($convention))->response()->setStatusCode(201);
    }

    public function show(InsuranceConvention $insuranceConvention): InsuranceConventionResource
    {
        return new InsuranceConventionResource($insuranceConvention->load('coverageRules'));
    }

    public function update(InsuranceConventionRequest $request, InsuranceConvention $insuranceConvention): InsuranceConventionResource
    {
        $insuranceConvention->update($request->validated());

        return new InsuranceConventionResource($insuranceConvention);
    }

    public function destroy(InsuranceConvention $insuranceConvention): JsonResponse
    {
        $insuranceConvention->delete();

        return response()->json(null, 204);
    }

    /**
     * Taux de couverture par catégorie de prestation (§2 cahier des
     * charges) : c'est ici que se configurent les taux différenciés,
     * plafonds et exclusions consommés par InsuranceCoverageService.
     */
    public function storeCoverageRule(InsuranceConventionCoverageRuleRequest $request, InsuranceConvention $insuranceConvention): JsonResponse
    {
        $rule = $insuranceConvention->coverageRules()->create($request->validated())->refresh();

        return (new InsuranceConventionCoverageRuleResource($rule))->response()->setStatusCode(201);
    }
}
