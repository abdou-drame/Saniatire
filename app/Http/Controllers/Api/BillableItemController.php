<?php

namespace App\Http\Controllers\Api;

use App\Domain\Facturation\Models\BillableItem;
use App\Http\Controllers\Controller;
use App\Http\Resources\BillableItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BillableItemController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:facturation.view', only: ['index']),
        ];
    }

    /**
     * Lignes générées automatiquement par BillingService, en attente d'être
     * reprises dans une facture. Aucune création manuelle exposée (§1 : pas
     * de ressaisie), uniquement une consultation filtrable.
     */
    public function index(Request $request): JsonResponse
    {
        $query = BillableItem::query()->orderByDesc('id');

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->integer('patient_id'));
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }

        return BillableItemResource::collection($query->paginate())->response();
    }
}
