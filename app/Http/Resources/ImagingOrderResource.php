<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImagingOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'patient_id' => $this->patient_id,
            'requester_type' => $this->requester_type,
            'requester_id' => $this->requester_id,
            'consultation_id' => $this->consultation_id,
            'appointment_id' => $this->appointment_id,
            'exam_type' => $this->exam_type,
            'status' => $this->status,
            'billing_status' => $this->billing_status,
            'ordered_at' => $this->ordered_at,
            'notes' => $this->notes,
            'studies' => ImagingStudyResource::collection($this->whenLoaded('studies')),
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                'first_name' => $this->patient->first_name,
                'last_name' => $this->patient->last_name,
                'patient_number' => $this->patient->patient_number,
            ]),
            'requester_label' => $this->whenLoaded('requester', fn () => $this->requester
                ? ($this->requester_type === \App\Domain\User\Models\User::class
                    ? trim("{$this->requester->first_name} {$this->requester->last_name}")
                    : $this->requester->nom)
                : null),
            'site' => $this->whenLoaded('site', fn () => $this->site ? ['id' => $this->site->id, 'name' => $this->site->name] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
