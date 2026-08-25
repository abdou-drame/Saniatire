<?php

namespace App\Http\Controllers\Api;

use App\Domain\Achats\Models\ApprovalRule;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApprovalRuleRequest;
use App\Http\Resources\ApprovalRuleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * CRUD des règles du circuit d'approbation des commandes (§2 cahier des
 * charges). Chaque structure définit ses propres niveaux/seuils/rôles via
 * ces endpoints — voir ApprovalRule pour le détail du mécanisme.
 */
class ApprovalRuleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:achats.view', only: ['index', 'show']),
            new Middleware('permission:achats.update', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(): JsonResponse
    {
        return ApprovalRuleResource::collection(ApprovalRule::query()->orderBy('level')->get())->response();
    }

    public function store(ApprovalRuleRequest $request): JsonResponse
    {
        $rule = ApprovalRule::create($request->validated())->refresh();

        return (new ApprovalRuleResource($rule))->response()->setStatusCode(201);
    }

    public function show(ApprovalRule $approvalRule): ApprovalRuleResource
    {
        return new ApprovalRuleResource($approvalRule);
    }

    public function update(ApprovalRuleRequest $request, ApprovalRule $approvalRule): ApprovalRuleResource
    {
        $approvalRule->update($request->validated());

        return new ApprovalRuleResource($approvalRule);
    }

    public function destroy(ApprovalRule $approvalRule): JsonResponse
    {
        $approvalRule->delete();

        return response()->json(null, 204);
    }
}
