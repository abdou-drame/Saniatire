<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DialysisSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dialysis_program_id' => $this->dialysis_program_id,
            'practitioner_id' => $this->practitioner_id,
            'session_date' => $this->session_date,
            'pre_weight_kg' => $this->pre_weight_kg,
            'post_weight_kg' => $this->post_weight_kg,
            'dry_weight_kg' => $this->dry_weight_kg,
            'duration_minutes' => $this->duration_minutes,
            'blood_flow_rate_ml_min' => $this->blood_flow_rate_ml_min,
            'ultrafiltration_volume_ml' => $this->ultrafiltration_volume_ml,
            'complications' => $this->complications,
            'status' => $this->status,
            'vitals' => DialysisSessionVitalResource::collection($this->whenLoaded('vitals')),
            'created_at' => $this->created_at,
        ];
    }
}
