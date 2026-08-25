<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeleconsultationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'appointment_id' => $this->appointment_id,
            'patient_id' => $this->patient_id,
            'practitioner_id' => $this->practitioner_id,
            'consultation_id' => $this->consultation_id,
            'statut' => $this->statut,
            'lien_session' => $this->lien_session,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
