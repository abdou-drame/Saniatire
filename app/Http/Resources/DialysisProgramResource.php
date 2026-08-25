<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DialysisProgramResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'patient_id' => $this->patient_id,
            'consultation_id' => $this->consultation_id,
            'frequency_per_week' => $this->frequency_per_week,
            'dry_weight_kg' => $this->dry_weight_kg,
            'vascular_access_type' => $this->vascular_access_type,
            'vascular_access_status' => $this->vascular_access_status,
            'status' => $this->status,
            'started_at' => $this->started_at?->format('Y-m-d'),
            'sessions' => DialysisSessionResource::collection($this->whenLoaded('sessions')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
