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
            'caissier_label' => $this->whenLoaded('caissier', fn () => $this->caissier ? trim("{$this->caissier->first_name} {$this->caissier->last_name}") : null),
            'site' => $this->whenLoaded('site', fn () => $this->site ? ['id' => $this->site->id, 'name' => $this->site->name] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
