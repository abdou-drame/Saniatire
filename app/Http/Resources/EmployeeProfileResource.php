<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'user_id' => $this->user_id,
            'date_embauche' => $this->date_embauche?->toDateString(),
            'type_contrat' => $this->type_contrat,
            'statut_emploi' => $this->statut_emploi,
            'qualification' => $this->qualification,
            'numero_ordre' => $this->numero_ordre,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
