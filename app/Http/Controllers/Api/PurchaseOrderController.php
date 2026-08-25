<?php

namespace App\Http\Controllers\Api;

use App\Domain\Achats\Models\ApprovalRule;
use App\Domain\Achats\Models\PurchaseOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:achats.view', only: ['index', 'show']),
            new Middleware('permission:achats.create', only: ['store']),
            new Middleware('permission:achats.update', only: ['submit']),
            new Middleware('permission:achats.cancel', only: ['cancel']),
            new Middleware('permission:achats.approve', only: ['approve']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $orders = PurchaseOrder::query()
            ->when($request->string('statut')->isNotEmpty(), fn ($q) => $q->where('statut', $request->string('statut')))
            ->with(['items', 'approvals', 'supplier:id,nom', 'site:id,name', 'createdBy:id,first_name,last_name'])
            ->orderByDesc('created_at')
            ->paginate();

        return PurchaseOrderResource::collection($orders)->response();
    }

    public function store(PurchaseOrderRequest $request): JsonResponse
    {
        $data = $request->validated();

        $order = DB::transaction(function () use ($data, $request) {
            $montantTotal = collect($data['items'])->sum(fn ($item) => $item['quantite_commandee'] * $item['prix_unitaire']);

            $order = PurchaseOrder::create([
                'site_id' => $data['site_id'],
                'supplier_id' => $data['supplier_id'],
                'created_by' => $request->user()->id,
                'montant_total' => $montantTotal,
                'statut' => 'brouillon',
            ]);

            foreach ($data['items'] as $item) {
                $order->items()->create($item);
            }

            return $order->refresh();
        });

        return (new PurchaseOrderResource($order->load('items')))->response()->setStatusCode(201);
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrder->load(['items', 'approvals', 'supplier:id,nom', 'site:id,name', 'createdBy:id,first_name,last_name']));
    }

    /**
     * Soumission d'une commande brouillon : génère le circuit d'approbation
     * à partir des règles configurées pour la structure (§2 cahier des
     * charges). Chaque règle dont le seuil est atteint par le montant de la
     * commande devient une ligne d'approbation en attente, dans l'ordre des
     * niveaux — snapshot au moment T pour ne pas être affecté par une
     * modification ultérieure des règles.
     */
    public function submit(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        abort_if($purchaseOrder->statut !== 'brouillon', 422, 'Seule une commande en brouillon peut être soumise.');
        abort_if($purchaseOrder->items()->count() === 0, 422, 'Une commande sans article ne peut pas être soumise.');

        DB::transaction(function () use ($purchaseOrder) {
            $rules = ApprovalRule::query()
                ->where('min_amount', '<=', $purchaseOrder->montant_total)
                ->orderBy('level')
                ->get();

            foreach ($rules as $rule) {
                $purchaseOrder->approvals()->create([
                    'approval_rule_id' => $rule->id,
                    'level' => $rule->level,
                    'min_amount' => $rule->min_amount,
                    'role_name' => $rule->role_name,
                    'statut' => 'en_attente',
                ]);
            }

            $purchaseOrder->update([
                'statut' => $rules->isEmpty() ? 'validee' : 'en_attente_validation',
            ]);
        });

        return new PurchaseOrderResource($purchaseOrder->load(['items', 'approvals']));
    }

    /**
     * Valide le niveau d'approbation courant (le plus petit "level" encore
     * en_attente). L'utilisateur doit détenir le rôle exigé par CE niveau —
     * rôle et seuil viennent tous deux de la ligne d'approbation, elle-même
     * copiée depuis approval_rules à la soumission : rien n'est codé en dur
     * ici.
     */
    public function approve(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        abort_if($purchaseOrder->statut !== 'en_attente_validation', 422, "Cette commande n'est pas en attente de validation.");

        DB::transaction(function () use ($request, $purchaseOrder) {
            $approval = $purchaseOrder->approvals()
                ->where('statut', 'en_attente')
                ->orderBy('level')
                ->lockForUpdate()
                ->first();

            abort_if($approval === null, 422, "Aucune approbation en attente pour cette commande.");
            abort_if(! $request->user()->hasRole($approval->role_name), 403, "Le rôle « {$approval->role_name} » est requis pour valider ce niveau.");

            $approval->update([
                'statut' => 'validee',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            $remaining = $purchaseOrder->approvals()->where('statut', 'en_attente')->exists();

            if (! $remaining) {
                $purchaseOrder->update(['statut' => 'validee']);
            }
        });

        return new PurchaseOrderResource($purchaseOrder->load(['items', 'approvals']));
    }

    public function cancel(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        abort_if(in_array($purchaseOrder->statut, ['recue_partielle', 'recue_totale', 'annulee'], true), 422, 'Cette commande ne peut plus être annulée.');

        $purchaseOrder->update(['statut' => 'annulee']);

        return new PurchaseOrderResource($purchaseOrder);
    }
}
