<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExternalPrescriberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'nom' => $this->nom,
            'specialite' => $this->specialite,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'statut' => $this->statut,
            'portal_activated_at' => $this->portal_activated_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
