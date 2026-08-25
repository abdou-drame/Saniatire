<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'gestionnaire_id' => $this->gestionnaire_id,
            'motif' => $this->motif,
            'description' => $this->description,
            'service_concerne' => $this->service_concerne,
            'statut' => $this->statut,
            'resolved_at' => $this->resolved_at,
            'closed_at' => $this->closed_at,
            'responses' => ComplaintResponseResource::collection($this->whenLoaded('responses')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
