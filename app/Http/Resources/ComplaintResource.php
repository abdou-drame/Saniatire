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
            'gestionnaire_label' => $this->whenLoaded('gestionnaire', fn () => $this->gestionnaire ? trim("{$this->gestionnaire->first_name} {$this->gestionnaire->last_name}") : null),
            'gestionnaire_role' => $this->whenLoaded('gestionnaire', fn () => $this->gestionnaire?->getRoleNames()->first()),
            'motif' => $this->motif,
            'description' => $this->description,
            'service_concerne' => $this->service_concerne,
            'origin' => $this->origin,
            'statut' => $this->statut,
            'resolved_at' => $this->resolved_at,
            'resolved_by' => $this->resolved_by,
            'resolved_by_label' => $this->whenLoaded('resolvedBy', fn () => $this->resolvedBy ? trim("{$this->resolvedBy->first_name} {$this->resolvedBy->last_name}") : null),
            'closed_at' => $this->closed_at,
            'closed_by' => $this->closed_by,
            'closed_by_label' => $this->whenLoaded('closedBy', fn () => $this->closedBy ? trim("{$this->closedBy->first_name} {$this->closedBy->last_name}") : null),
            'responses' => ComplaintResponseResource::collection($this->whenLoaded('responses')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
