<?php

namespace App\Http\Controllers\Api;

use App\Domain\Assurance\Models\InsuranceConventionCoverageRule;
use App\Http\Controllers\Controller;
use App\Http\Requests\InsuranceConventionCoverageRuleRequest;
use App\Http\Resources\InsuranceConventionCoverageRuleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class InsuranceConventionCoverageRuleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:assurance.update', only: ['update']),
            new Middleware('permission:assurance.delete', only: ['destroy']),
        ];
    }

    public function update(InsuranceConventionCoverageRuleRequest $request, InsuranceConventionCoverageRule $insuranceConventionCoverageRule): InsuranceConventionCoverageRuleResource
    {
        $insuranceConventionCoverageRule->update($request->validated());

        return new InsuranceConventionCoverageRuleResource($insuranceConventionCoverageRule);
    }

    public function destroy(InsuranceConventionCoverageRule $insuranceConventionCoverageRule): JsonResponse
    {
        $insuranceConventionCoverageRule->delete();

        return response()->json(null, 204);
    }
}
