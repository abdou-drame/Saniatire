<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'product_id' => $this->product_id,
            'site_id' => $this->site_id,
            'supplier_id' => $this->supplier_id,
            'numero_lot' => $this->numero_lot,
            'date_peremption' => $this->date_peremption,
            'quantite_stock' => $this->quantite_stock,
            'prix_achat_unitaire' => $this->prix_achat_unitaire,
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id' => $this->product->id,
                'nom_commercial' => $this->product->nom_commercial,
                'dci' => $this->product->dci,
                'unite_vente' => $this->product->unite_vente,
            ] : null),
            'site' => $this->whenLoaded('site', fn () => $this->site ? ['id' => $this->site->id, 'name' => $this->site->name] : null),
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? ['id' => $this->supplier->id, 'nom' => $this->supplier->nom] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
