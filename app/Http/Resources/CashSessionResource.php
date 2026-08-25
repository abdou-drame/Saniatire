<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'caissier_id' => $this->caissier_id,
            'montant_ouverture' => $this->montant_ouverture,
            'montant_cloture' => $this->montant_cloture,
            'ecart' => $this->ecart,
            'ouverte_le' => $this->ouverte_le,
            'fermee_le' => $this->fermee_le,
            'statut' => $this->statut,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
