<?php

namespace App\Http\Controllers\Api;

use App\Domain\Pharmacie\Models\StockMovement;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class PharmacieRapportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:stock.view'),
        ];
    }

    /**
     * Rapport des ventes (sorties dispensées) sur une période.
     * GET /stock/rapports/ventes?date_debut=&date_fin=&site_id=
     */
    public function ventes(Request $request): JsonResponse
    {
        $request->validate([
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
        ]);

        $query = StockMovement::query()
            ->with(['productBatch.product:id,nom_commercial,dci,unite_vente,prix_vente_unitaire', 'site:id,name'])
            ->where('type', 'sortie')
            ->whereNotNull('dispensed_for_type')
            ->when($request->date('date_debut'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date('date_fin'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($request->integer('site_id'), fn ($q, $id) => $q->where('site_id', $id));

        $movements = $query->get();

        $lignes = $movements->map(function (StockMovement $m) {
            $product = $m->productBatch?->product;
            $prixVente = $product?->prix_vente_unitaire ?? $m->productBatch?->prix_achat_unitaire ?? 0;
            $prixAchat = $m->productBatch?->prix_achat_unitaire ?? 0;

            return [
                'id' => $m->id,
                'date' => $m->created_at->toDateString(),
                'product_id' => $product?->id,
                'nom_commercial' => $product?->nom_commercial,
                'dci' => $product?->dci,
                'unite_vente' => $product?->unite_vente,
                'site' => $m->site?->name,
                'quantite' => $m->quantite,
                'prix_vente_unitaire' => (float) $prixVente,
                'prix_achat_unitaire' => (float) $prixAchat,
                'montant_vente' => round($m->quantite * $prixVente, 2),
                'montant_achat' => round($m->quantite * $prixAchat, 2),
                'marge' => round($m->quantite * ($prixVente - $prixAchat), 2),
                'dispensed_for_type' => $m->dispensed_for_type,
                'dispensed_for_id' => $m->dispensed_for_id,
            ];
        });

        $totaux = [
            'total_quantite' => $lignes->sum('quantite'),
            'total_ventes' => round($lignes->sum('montant_vente'), 2),
            'total_achats' => round($lignes->sum('montant_achat'), 2),
            'total_marge' => round($lignes->sum('marge'), 2),
            'taux_marge' => $lignes->sum('montant_vente') > 0
                ? round($lignes->sum('marge') / $lignes->sum('montant_vente') * 100, 1)
                : 0,
        ];

        return response()->json([
            'data' => $lignes->values(),
            'totaux' => $totaux,
        ]);
    }

    /**
     * Valorisation du stock actuel (par produit, tous lots confondus).
     * GET /stock/rapports/valorisation?site_id=
     */
    public function valorisation(Request $request): JsonResponse
    {
        $request->validate([
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
        ]);

        $rows = DB::table('product_batches as pb')
            ->join('products as p', 'p.id', '=', 'pb.product_id')
            ->leftJoin('sites as si', 'si.id', '=', 'pb.site_id')
            ->select(
                'p.id as product_id',
                'p.nom_commercial',
                'p.dci',
                'p.unite_vente',
                'p.prix_vente_unitaire',
                'si.id as site_id',
                'si.name as site_name',
                DB::raw('SUM(pb.quantite_stock) as stock_total'),
                DB::raw('AVG(pb.prix_achat_unitaire) as prix_achat_moyen'),
            )
            ->where('pb.structure_id', auth()->user()->structure_id)
            ->when($request->integer('site_id'), fn ($q, $id) => $q->where('pb.site_id', $id))
            ->where('pb.quantite_stock', '>', 0)
            ->groupBy('p.id', 'p.nom_commercial', 'p.dci', 'p.unite_vente', 'p.prix_vente_unitaire', 'si.id', 'si.name')
            ->orderBy('p.nom_commercial')
            ->get();

        $lignes = $rows->map(function ($row) {
            $prixVente = (float) ($row->prix_vente_unitaire ?? $row->prix_achat_moyen ?? 0);
            $prixAchat = (float) ($row->prix_achat_moyen ?? 0);
            $stock = (int) $row->stock_total;

            return [
                'product_id' => $row->product_id,
                'nom_commercial' => $row->nom_commercial,
                'dci' => $row->dci,
                'unite_vente' => $row->unite_vente,
                'site_id' => $row->site_id,
                'site_name' => $row->site_name,
                'stock_total' => $stock,
                'prix_achat_moyen' => round($prixAchat, 2),
                'prix_vente_unitaire' => round($prixVente, 2),
                'valeur_achat' => round($stock * $prixAchat, 2),
                'valeur_vente' => round($stock * $prixVente, 2),
            ];
        });

        $totaux = [
            'valeur_achat_totale' => round($lignes->sum('valeur_achat'), 2),
            'valeur_vente_totale' => round($lignes->sum('valeur_vente'), 2),
            'plus_value_potentielle' => round($lignes->sum('valeur_vente') - $lignes->sum('valeur_achat'), 2),
        ];

        return response()->json([
            'data' => $lignes->values(),
            'totaux' => $totaux,
        ]);
    }

    /**
     * Top produits par volume de ventes ou chiffre d'affaires.
     * GET /stock/rapports/top-produits?date_debut=&date_fin=&limite=10&tri=quantite|chiffre_affaires
     */
    public function topProduits(Request $request): JsonResponse
    {
        $request->validate([
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:100'],
            'tri' => ['nullable', 'in:quantite,chiffre_affaires'],
        ]);

        $limite = $request->integer('limite', 10);
        $tri = $request->string('tri', 'quantite')->toString();

        $rows = DB::table('stock_movements as sm')
            ->join('product_batches as pb', 'pb.id', '=', 'sm.product_batch_id')
            ->join('products as p', 'p.id', '=', 'pb.product_id')
            ->select(
                'p.id as product_id',
                'p.nom_commercial',
                'p.dci',
                'p.unite_vente',
                'p.prix_vente_unitaire',
                DB::raw('SUM(sm.quantite) as total_quantite'),
                DB::raw('SUM(sm.quantite * COALESCE(p.prix_vente_unitaire, pb.prix_achat_unitaire, 0)) as chiffre_affaires'),
                DB::raw('SUM(sm.quantite * (COALESCE(p.prix_vente_unitaire, pb.prix_achat_unitaire, 0) - COALESCE(pb.prix_achat_unitaire, 0))) as marge_totale'),
            )
            ->where('sm.structure_id', auth()->user()->structure_id)
            ->where('sm.type', 'sortie')
            ->whereNotNull('sm.dispensed_for_type')
            ->when($request->date('date_debut'), fn ($q, $d) => $q->whereDate('sm.created_at', '>=', $d))
            ->when($request->date('date_fin'), fn ($q, $d) => $q->whereDate('sm.created_at', '<=', $d))
            ->groupBy('p.id', 'p.nom_commercial', 'p.dci', 'p.unite_vente', 'p.prix_vente_unitaire')
            ->orderByDesc($tri === 'chiffre_affaires' ? 'chiffre_affaires' : 'total_quantite')
            ->limit($limite)
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'product_id' => $r->product_id,
                'nom_commercial' => $r->nom_commercial,
                'dci' => $r->dci,
                'unite_vente' => $r->unite_vente,
                'total_quantite' => (int) $r->total_quantite,
                'chiffre_affaires' => round((float) $r->chiffre_affaires, 2),
                'marge_totale' => round((float) $r->marge_totale, 2),
            ])->values(),
        ]);
    }
}
