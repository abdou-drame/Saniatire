<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsuranceConventionCoverageRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'insurance_convention_id' => $this->insurance_convention_id,
            'categorie' => $this->categorie,
            'taux_couverture' => $this->taux_couverture,
            'plafond_montant' => $this->plafond_montant,
            'exclu' => $this->exclu,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
