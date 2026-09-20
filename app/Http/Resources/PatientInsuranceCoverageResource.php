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
            'patient' => $this->whenLoaded('patient', fn () => $this->patient ? [
                'id' => $this->patient->id,
                'first_name' => $this->patient->first_name,
                'last_name' => $this->patient->last_name,
                'patient_number' => $this->patient->patient_number,
            ] : null),
            'convention' => $this->whenLoaded('convention', fn () => $this->convention ? [
                'id' => $this->convention->id,
                'nom' => $this->convention->nom,
                'provider_nom' => $this->convention->provider?->nom,
            ] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
