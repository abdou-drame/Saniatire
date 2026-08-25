<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillableItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'patient_id' => $this->patient_id,
            'billable_type' => $this->billable_type,
            'billable_id' => $this->billable_id,
            'categorie' => $this->categorie,
            'code_prestation' => $this->code_prestation,
            'libelle' => $this->libelle,
            'quantite' => $this->quantite,
            'prix_unitaire' => $this->prix_unitaire,
            'montant_total' => $this->montant_total,
            'statut' => $this->statut,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
