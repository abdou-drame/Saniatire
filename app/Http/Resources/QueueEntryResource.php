<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QueueEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'patient_id' => $this->patient_id,
            'appointment_id' => $this->appointment_id,
            'practitioner_id' => $this->practitioner_id,
            'service' => $this->service,
            'priority' => $this->priority,
            'status' => $this->status,
            'arrived_at' => $this->arrived_at?->toIso8601String(),
            'called_at' => $this->called_at?->toIso8601String(),
            'in_consultation_at' => $this->in_consultation_at?->toIso8601String(),
            'exited_at' => $this->exited_at?->toIso8601String(),
            'wait_minutes' => $this->waitMinutes(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
