<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KineProgramResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'patient_id' => $this->patient_id,
            'consultation_id' => $this->consultation_id,
            'affected_area' => $this->affected_area,
            'initial_range_of_motion' => $this->initial_range_of_motion,
            'initial_pain_scale' => $this->initial_pain_scale,
            'objectives' => $this->objectives,
            'status' => $this->status,
            'started_at' => $this->started_at?->format('Y-m-d'),
            'sessions' => KineSessionResource::collection($this->whenLoaded('sessions')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
