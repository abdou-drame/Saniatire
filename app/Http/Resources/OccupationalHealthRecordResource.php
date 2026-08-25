<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OccupationalHealthRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'patient_id' => $this->patient_id,
            'consultation_id' => $this->consultation_id,
            'visit_type' => $this->visit_type,
            'fitness_status' => $this->fitness_status,
            'restrictions' => $this->restrictions,
            'risk_exposures' => $this->risk_exposures,
            'visit_date' => $this->visit_date?->format('Y-m-d'),
            'next_visit_due_at' => $this->next_visit_due_at?->format('Y-m-d'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
