<?php

namespace App\Http\Controllers\Api;

use App\Domain\Pharmacie\Models\ProductBatch;
use App\Domain\Pharmacie\Models\StockThreshold;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductBatchResource;
use App\Http\Resources\StockThresholdResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class StockAlertController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:stock.view', only: ['lowThreshold', 'expiry']),
        ];
    }

    /**
     * Produits dont le stock total (toutes lots confondus) sur un site est
     * en-dessous du seuil minimum configuré pour ce produit/site.
     */
    public function lowThreshold(Request $request): JsonResponse
    {
        $thresholds = StockThreshold::query()
            ->with(['product:id,nom_commercial,dci,unite_vente', 'site:id,name'])
            ->when($request->integer('site_id'), fn ($q, $id) => $q->where('site_id', $id))
            ->get();

        $alerts = $thresholds->map(function (StockThreshold $threshold) {
            $stockActuel = ProductBatch::where('product_id', $threshold->product_id)
                ->where('site_id', $threshold->site_id)
                ->sum('quantite_stock');

            return [
                'product_id' => $threshold->product_id,
                'site_id' => $threshold->site_id,
                'seuil_minimum' => $threshold->seuil_minimum,
                'stock_actuel' => (int) $stockActuel,
                'product' => $threshold->product ? [
                    'id' => $threshold->product->id,
                    'nom_commercial' => $threshold->product->nom_commercial,
                    'dci' => $threshold->product->dci,
                    'unite_vente' => $threshold->product->unite_vente,
                ] : null,
                'site' => $threshold->site ? ['id' => $threshold->site->id, 'name' => $threshold->site->name] : null,
            ];
        })->filter(fn (array $row) => $row['stock_actuel'] < $row['seuil_minimum'])->values();

        return response()->json(['data' => $alerts]);
    }

    /**
     * Lots déjà expirés ou expirant dans la fenêtre donnée (en jours,
     * défaut 30).
     */
    public function expiry(Request $request): JsonResponse
    {
        $window = (int) $request->integer('window', 30);

        $batches = ProductBatch::query()
            ->with(['product:id,nom_commercial,dci,unite_vente', 'site:id,name', 'supplier:id,nom'])
            ->where('date_peremption', '<=', now()->addDays($window))
            ->when($request->integer('site_id'), fn ($q, $id) => $q->where('site_id', $id))
            ->orderBy('date_peremption')
            ->get();

        return ProductBatchResource::collection($batches)->response();
    }
}
