<?php

namespace App\Http\Controllers\Api;

use App\Domain\Achats\Models\PurchaseOrderItem;
use App\Domain\Pharmacie\Models\ProductBatch;
use App\Domain\Pharmacie\Models\StockMovement;
use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrderReceptionRequest;
use App\Http\Resources\PurchaseOrderReceptionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class PurchaseOrderReceptionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:stock.create', only: ['store']),
        ];
    }

    /**
     * Réceptionne (totalement ou partiellement) un article d'une commande.
     * Une réception "conforme" génère automatiquement le mouvement de
     * stock d'entrée correspondant (§2 cahier des charges) : le lot est
     * retrouvé ou créé (verrouillé pour éviter une course avec un autre
     * mouvement concurrent sur le même lot), puis le statut agrégé de la
     * commande est recalculé. Une réception "non_conforme" est tracée mais
     * ne touche jamais au stock.
     */
    public function store(PurchaseOrderReceptionRequest $request, PurchaseOrderItem $purchaseOrderItem): JsonResponse
    {
        $data = $request->validated();

        abort_if(
            in_array($purchaseOrderItem->purchaseOrder->statut, ['brouillon', 'en_attente_validation', 'annulee'], true),
            422,
            'Cette commande ne peut pas encore être réceptionnée.'
        );

        $reception = DB::transaction(function () use ($data, $request, $purchaseOrderItem) {
            $reception = $purchaseOrderItem->receptions()->create([
                ...$data,
                'receptionne_par' => $request->user()->id,
            ]);

            if ($data['controle_qualite'] === 'conforme') {
                $order = $purchaseOrderItem->purchaseOrder;

                $batch = ProductBatch::where('product_id', $purchaseOrderItem->product_id)
                    ->where('site_id', $order->site_id)
                    ->where('numero_lot', $data['numero_lot'])
                    ->lockForUpdate()
                    ->first();

                if ($batch) {
                    $batch->increment('quantite_stock', $data['quantite_recue']);
                } else {
                    $batch = ProductBatch::create([
                        'product_id' => $purchaseOrderItem->product_id,
                        'site_id' => $order->site_id,
                        'supplier_id' => $order->supplier_id,
                        'numero_lot' => $data['numero_lot'],
                        'date_peremption' => $data['date_peremption'],
                        'quantite_stock' => $data['quantite_recue'],
                        'prix_achat_unitaire' => $purchaseOrderItem->prix_unitaire,
                    ]);
                }

                StockMovement::create([
                    'product_batch_id' => $batch->id,
                    'site_id' => $order->site_id,
                    'type' => 'entree',
                    'quantite' => $data['quantite_recue'],
                    'motif' => "Réception commande #{$order->id}",
                    'user_id' => $request->user()->id,
                ]);

                $purchaseOrderItem->increment('quantite_recue', $data['quantite_recue']);

                $order->refresh();
                $totalCommande = $order->items()->sum('quantite_commandee');
                $totalRecu = $order->items()->sum('quantite_recue');

                $order->update([
                    'statut' => $totalRecu >= $totalCommande ? 'recue_totale' : 'recue_partielle',
                ]);
            }

            return $reception;
        });

        return (new PurchaseOrderReceptionResource($reception))->response()->setStatusCode(201);
    }
}
