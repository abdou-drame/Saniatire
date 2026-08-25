<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientSatisfactionSurveyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'prestation_type' => $this->prestation_type,
            'prestation_id' => $this->prestation_id,
            'service' => $this->service,
            'note' => $this->note,
            'commentaire' => $this->commentaire,
            'date' => $this->date?->toDateString(),
            'created_at' => $this->created_at,
        ];
    }
}
