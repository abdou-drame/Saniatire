<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lab_sample_id' => $this->lab_sample_id,
            'lab_order_item_id' => $this->lab_order_item_id,
            'value' => $this->value,
            'unit' => $this->unit,
            'reference_min' => $this->reference_min,
            'reference_max' => $this->reference_max,
            'interpretation' => $this->interpretation,
            'status' => $this->status,
            'technical_validated_by' => $this->technical_validated_by,
            'technical_validator_label' => $this->whenLoaded('technicalValidator', fn () => $this->technicalValidator ? trim("{$this->technicalValidator->first_name} {$this->technicalValidator->last_name}") : null),
            'technical_validator_role' => $this->whenLoaded('technicalValidator', fn () => $this->technicalValidator?->getRoleNames()->first()),
            'technical_validated_at' => $this->technical_validated_at,
            'biological_validated_by' => $this->biological_validated_by,
            'biological_validator_label' => $this->whenLoaded('biologicalValidator', fn () => $this->biologicalValidator ? trim("{$this->biologicalValidator->first_name} {$this->biologicalValidator->last_name}") : null),
            'biological_validator_role' => $this->whenLoaded('biologicalValidator', fn () => $this->biologicalValidator?->getRoleNames()->first()),
            'biological_validated_at' => $this->biological_validated_at,
            'created_at' => $this->created_at,
        ];
    }
}
