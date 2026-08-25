<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DentalTreatmentPlanItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dental_treatment_plan_id' => $this->dental_treatment_plan_id,
            'tooth_fdi' => $this->tooth_fdi,
            'act_type' => $this->act_type,
            'status' => $this->status,
            'planned_at' => $this->planned_at?->format('Y-m-d'),
        ];
    }
}
