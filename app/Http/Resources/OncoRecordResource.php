<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OncoRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'patient_id' => $this->patient_id,
            'consultation_id' => $this->consultation_id,
            'cancer_type' => $this->cancer_type,
            'stage_t' => $this->stage_t,
            'stage_n' => $this->stage_n,
            'stage_m' => $this->stage_m,
            'protocol_name' => $this->protocol_name,
            'treatment_line' => $this->treatment_line,
            'diagnosed_at' => $this->diagnosed_at?->format('Y-m-d'),
            'chemo_cycles' => OncoChemoCycleResource::collection($this->whenLoaded('chemoCycles')),
            'response_evaluations' => OncoResponseEvaluationResource::collection($this->whenLoaded('responseEvaluations')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
