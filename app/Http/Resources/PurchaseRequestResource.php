<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'product_id' => $this->product_id,
            'demandeur_id' => $this->demandeur_id,
            'quantite' => $this->quantite,
            'statut' => $this->statut,
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id' => $this->product->id,
                'nom_commercial' => $this->product->nom_commercial,
                'dci' => $this->product->dci,
                'unite_vente' => $this->product->unite_vente,
            ] : null),
            'site' => $this->whenLoaded('site', fn () => $this->site ? ['id' => $this->site->id, 'name' => $this->site->name] : null),
            'demandeur_label' => $this->whenLoaded('demandeur', fn () => $this->demandeur ? trim("{$this->demandeur->first_name} {$this->demandeur->last_name}") : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
