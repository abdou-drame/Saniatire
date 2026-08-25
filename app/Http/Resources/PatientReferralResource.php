<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientReferralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_origine_id' => $this->structure_origine_id,
            'site_origine_id' => $this->site_origine_id,
            'structure_destination_id' => $this->structure_destination_id,
            'patient_id' => $this->patient_id,
            'praticien_referent_id' => $this->praticien_referent_id,
            'motif' => $this->motif,
            'statut' => $this->statut,
            'compte_rendu_retour' => $this->compte_rendu_retour,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
