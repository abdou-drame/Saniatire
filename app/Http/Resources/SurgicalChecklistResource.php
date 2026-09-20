<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SurgicalChecklistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'surgical_procedure_id' => $this->surgical_procedure_id,
            'step' => $this->step,
            'items' => $this->items,
            'validated_by' => $this->validated_by,
            'validator_label' => $this->whenLoaded('validator', fn () => $this->validator ? trim("{$this->validator->first_name} {$this->validator->last_name}") : null),
            'validator_role' => $this->whenLoaded('validator', fn () => $this->validator?->getRoleNames()->first()),
            'validated_at' => $this->validated_at,
            'created_at' => $this->created_at,
        ];
    }
}
