<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'patient_id' => $this->patient_id,
            'practitioner_id' => $this->practitioner_id,
            'appointment_series_id' => $this->appointment_series_id,
            'resource_name' => $this->resource_name,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'reason' => $this->reason,
            'status' => $this->status,
            'is_recurring' => $this->is_recurring,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
