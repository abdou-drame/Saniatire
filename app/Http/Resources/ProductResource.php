<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'generic_catalog_ref' => $this->generic_catalog_ref,
            'nom_commercial' => $this->nom_commercial,
            'dci' => $this->dci,
            'forme_galenique' => $this->forme_galenique,
            'dosage' => $this->dosage,
            'categorie' => $this->categorie,
            'unite_vente' => $this->unite_vente,
            'actif' => $this->actif,
            'stock_total' => $this->when($this->stock_total !== null, fn () => (int) $this->stock_total),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
