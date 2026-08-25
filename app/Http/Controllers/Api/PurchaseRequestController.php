<?php

namespace App\Http\Controllers\Api;

use App\Domain\Achats\Models\PurchaseRequest as PurchaseRequestModel;
use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseRequestRequest;
use App\Http\Resources\PurchaseRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PurchaseRequestController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:achats.view', only: ['index', 'show']),
            new Middleware('permission:achats.create', only: ['store']),
            new Middleware('permission:achats.validate', only: ['approve', 'reject']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $requests = PurchaseRequestModel::query()
            ->with(['product:id,nom_commercial,dci,unite_vente', 'site:id,name', 'demandeur:id,first_name,last_name'])
            ->when($request->string('statut')->isNotEmpty(), fn ($q) => $q->where('statut', $request->string('statut')))
            ->orderByDesc('created_at')
            ->paginate();

        return PurchaseRequestResource::collection($requests)->response();
    }

    public function store(PurchaseRequestRequest $request): JsonResponse
    {
        $purchaseRequest = PurchaseRequestModel::create([
            ...$request->validated(),
            'demandeur_id' => $request->user()->id,
        ])->refresh();

        return (new PurchaseRequestResource($purchaseRequest))->response()->setStatusCode(201);
    }

    public function show(PurchaseRequestModel $purchaseRequest): PurchaseRequestResource
    {
        return new PurchaseRequestResource($purchaseRequest->load(['product:id,nom_commercial,dci,unite_vente', 'site:id,name', 'demandeur:id,first_name,last_name']));
    }

    public function approve(PurchaseRequestModel $purchaseRequest): PurchaseRequestResource
    {
        abort_if($purchaseRequest->statut !== 'demandee', 422, "Cette demande n'est plus en attente de validation.");

        $purchaseRequest->update(['statut' => 'validee']);

        return new PurchaseRequestResource($purchaseRequest);
    }

    public function reject(PurchaseRequestModel $purchaseRequest): PurchaseRequestResource
    {
        abort_if($purchaseRequest->statut !== 'demandee', 422, "Cette demande n'est plus en attente de validation.");

        $purchaseRequest->update(['statut' => 'rejetee']);

        return new PurchaseRequestResource($purchaseRequest);
    }
}
