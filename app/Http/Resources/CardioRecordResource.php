<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CardioRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'patient_id' => $this->patient_id,
            'consultation_id' => $this->consultation_id,
            'risk_factors' => $this->risk_factors,
            'current_treatment' => $this->current_treatment,
            'examined_at' => $this->examined_at?->format('Y-m-d'),
            'readings' => CardioReadingResource::collection($this->whenLoaded('readings')),
            'ecg_results' => CardioEcgResultResource::collection($this->whenLoaded('ecgResults')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
