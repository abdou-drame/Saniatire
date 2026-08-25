<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MentalHealthRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'patient_id' => $this->patient_id,
            'consultation_id' => $this->consultation_id,
            'consultation_reason' => $this->consultation_reason,
            'clinical_evaluation' => $this->clinical_evaluation,
            'ongoing_treatment' => $this->ongoing_treatment,
            'scale_scores' => MentalHealthScaleScoreResource::collection($this->whenLoaded('scaleScores')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
