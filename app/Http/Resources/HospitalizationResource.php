<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HospitalizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'patient_id' => $this->patient_id,
            'bed_id' => $this->bed_id,
            'ward_id' => $this->ward_id,
            'attending_physician_id' => $this->attending_physician_id,
            'admitted_at' => $this->admitted_at,
            'admission_reason' => $this->admission_reason,
            'discharged_at' => $this->discharged_at,
            'discharge_summary' => $this->discharge_summary,
            'status' => $this->status,
            'daily_notes' => HospitalizationDailyNoteResource::collection($this->whenLoaded('dailyNotes')),
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                'first_name' => $this->patient->first_name,
                'last_name' => $this->patient->last_name,
                'patient_number' => $this->patient->patient_number,
            ]),
            'bed' => $this->whenLoaded('bed', fn () => $this->bed ? [
                'id' => $this->bed->id,
                'room_number' => $this->bed->room_number,
                'bed_label' => $this->bed->bed_label,
            ] : null),
            'ward' => $this->whenLoaded('ward', fn () => $this->ward ? [
                'id' => $this->ward->id,
                'name' => $this->ward->name,
            ] : null),
            'attending_physician_label' => $this->whenLoaded('attendingPhysician', fn () => $this->attendingPhysician
                ? trim("{$this->attendingPhysician->first_name} {$this->attendingPhysician->last_name}")
                : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
