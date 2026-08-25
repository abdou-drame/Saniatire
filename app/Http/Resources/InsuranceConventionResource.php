<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsuranceConventionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'insurance_provider_id' => $this->insurance_provider_id,
            'nom' => $this->nom,
            'date_debut' => $this->date_debut,
            'date_fin' => $this->date_fin,
            'actif' => $this->actif,
            'coverage_rules' => InsuranceConventionCoverageRuleResource::collection($this->whenLoaded('coverageRules')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
