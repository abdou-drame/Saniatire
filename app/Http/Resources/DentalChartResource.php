<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DentalChartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'patient_id' => $this->patient_id,
            'consultation_id' => $this->consultation_id,
            'tooth_states' => DentalToothStateResource::collection($this->whenLoaded('toothStates')),
            'procedures' => DentalProcedureResource::collection($this->whenLoaded('procedures')),
            'treatment_plans' => DentalTreatmentPlanResource::collection($this->whenLoaded('treatmentPlans')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
