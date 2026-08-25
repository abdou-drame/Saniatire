<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaternityPostpartumVisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'maternity_record_id' => $this->maternity_record_id,
            'practitioner_id' => $this->practitioner_id,
            'visit_date' => $this->visit_date?->format('Y-m-d'),
            'blood_pressure_systolic' => $this->blood_pressure_systolic,
            'blood_pressure_diastolic' => $this->blood_pressure_diastolic,
            'temperature_c' => $this->temperature_c,
            'bleeding_status' => $this->bleeding_status,
            'breastfeeding_status' => $this->breastfeeding_status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
