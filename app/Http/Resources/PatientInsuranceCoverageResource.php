<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientInsuranceCoverageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'patient_id' => $this->patient_id,
            'insurance_convention_id' => $this->insurance_convention_id,
            'numero_adherent' => $this->numero_adherent,
            'beneficiaire_type' => $this->beneficiaire_type,
            'date_debut' => $this->date_debut,
            'date_fin' => $this->date_fin,
            'actif' => $this->actif,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
