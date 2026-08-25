<?php

namespace App\Http\Controllers\Api;

use App\Domain\Pharmacie\Models\ProductBatch;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockMovementRequest;
use App\Http\Resources\StockMovementResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class StockMovementController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:stock.view', only: ['index']),
            new Middleware('permission:stock.dispense', only: ['store']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $movements = \App\Domain\Pharmacie\Models\StockMovement::query()
            ->with(['productBatch.product:id,nom_commercial,dci,unite_vente', 'site:id,name', 'destinationSite:id,name', 'user:id,first_name,last_name'])
            ->when($request->integer('product_batch_id'), fn ($q, $id) => $q->where('product_batch_id', $id))
            ->when($request->string('type')->isNotEmpty(), fn ($q) => $q->where('type', $request->string('type')))
            ->orderByDesc('created_at')
            ->paginate();

        return StockMovementResource::collection($movements)->response();
    }

    /**
     * Règle bloquante (§1/§5 cahier des charges) : un lot périmé ou à
     * quantité nulle ne peut jamais être sélectionné pour une sortie.
     * lockForUpdate() sérialise les mouvements concurrents sur le même lot
     * pour fermer la fenêtre de course entre la lecture du stock et sa
     * décrémentation, sur le même modèle que le verrouillage de lit en
     * hospitalisation.
     */
    public function store(StockMovementRequest $request): JsonResponse
    {
        $data = $request->validated();

        $movement = DB::transaction(function () use ($data, $request) {
            $batch = ProductBatch::whereKey($data['product_batch_id'])->lockForUpdate()->firstOrFail();

            match ($data['type']) {
                'sortie' => $this->applySortie($batch, $data['quantite']),
                'entree' => $batch->increment('quantite_stock', $data['quantite']),
                'ajustement' => $this->applyAjustement($batch, $data['quantite']),
                'transfert' => $this->applyTransfert($batch, $data['quantite'], $data['destination_site_id']),
            };

            return \App\Domain\Pharmacie\Models\StockMovement::create([
                ...$data,
                'user_id' => $request->user()->id,
            ])->refresh();
        });

        return (new StockMovementResource($movement))->response()->setStatusCode(201);
    }

    private function applySortie(ProductBatch $batch, int $quantite): void
    {
        abort_if($batch->quantite_stock <= 0, 422, 'Ce lot est épuisé et ne peut pas être sélectionné pour une dispensation.');
        abort_if($batch->isExpired(), 422, 'Ce lot est périmé et ne peut pas être sélectionné pour une dispensation.');
        abort_if($quantite > $batch->quantite_stock, 422, 'Quantité insuffisante en stock pour ce lot.');

        $batch->decrement('quantite_stock', $quantite);
    }

    private function applyAjustement(ProductBatch $batch, int $delta): void
    {
        abort_if($batch->quantite_stock + $delta < 0, 422, 'Cet ajustement rendrait le stock négatif.');

        $batch->update(['quantite_stock' => $batch->quantite_stock + $delta]);
    }

    private function applyTransfert(ProductBatch $batch, int $quantite, int $destinationSiteId): void
    {
        abort_if($quantite > $batch->quantite_stock, 422, 'Quantité insuffisante en stock pour ce transfert.');

        $batch->decrement('quantite_stock', $quantite);

        $destinationBatch = ProductBatch::where('product_id', $batch->product_id)
            ->where('site_id', $destinationSiteId)
            ->where('numero_lot', $batch->numero_lot)
            ->lockForUpdate()
            ->first();

        if ($destinationBatch) {
            $destinationBatch->increment('quantite_stock', $quantite);
        } else {
            ProductBatch::create([
                'structure_id' => $batch->structure_id,
                'product_id' => $batch->product_id,
                'site_id' => $destinationSiteId,
                'supplier_id' => $batch->supplier_id,
                'numero_lot' => $batch->numero_lot,
                'date_peremption' => $batch->date_peremption,
                'quantite_stock' => $quantite,
                'prix_achat_unitaire' => $batch->prix_achat_unitaire,
            ]);
        }
    }
}
