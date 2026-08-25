<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'nom' => $this->nom,
            'contact' => $this->contact,
            'conditions_commerciales' => $this->conditions_commerciales,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
