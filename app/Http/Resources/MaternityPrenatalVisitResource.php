<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaternityPrenatalVisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'maternity_record_id' => $this->maternity_record_id,
            'practitioner_id' => $this->practitioner_id,
            'visit_number' => $this->visit_number,
            'gestational_age_weeks' => $this->gestational_age_weeks,
            'weight_kg' => $this->weight_kg,
            'blood_pressure_systolic' => $this->blood_pressure_systolic,
            'blood_pressure_diastolic' => $this->blood_pressure_diastolic,
            'fundal_height_cm' => $this->fundal_height_cm,
            'fetal_movements' => $this->fetal_movements,
            'fetal_heart_rate' => $this->fetal_heart_rate,
            'visit_date' => $this->visit_date?->format('Y-m-d'),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
