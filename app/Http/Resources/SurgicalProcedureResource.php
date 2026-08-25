<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SurgicalProcedureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'patient_id' => $this->patient_id,
            'hospitalization_id' => $this->hospitalization_id,
            'surgeon_id' => $this->surgeon_id,
            'anesthesiologist_id' => $this->anesthesiologist_id,
            'operating_room' => $this->operating_room,
            'procedure_type' => $this->procedure_type,
            'scheduled_at' => $this->scheduled_at,
            'performed_at' => $this->performed_at,
            'status' => $this->status,
            'checklists' => SurgicalChecklistResource::collection($this->whenLoaded('checklists')),
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                'first_name' => $this->patient->first_name,
                'last_name' => $this->patient->last_name,
                'patient_number' => $this->patient->patient_number,
            ]),
            'surgeon_label' => $this->whenLoaded('surgeon', fn () => $this->surgeon
                ? trim("{$this->surgeon->first_name} {$this->surgeon->last_name}")
                : null),
            'anesthesiologist_label' => $this->whenLoaded('anesthesiologist', fn () => $this->anesthesiologist
                ? trim("{$this->anesthesiologist->first_name} {$this->anesthesiologist->last_name}")
                : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
