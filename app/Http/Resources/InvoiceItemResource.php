<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'billable_item_id' => $this->billable_item_id,
            'libelle' => $this->libelle,
            'categorie' => $this->categorie,
            'quantite' => $this->quantite,
            'prix_unitaire' => $this->prix_unitaire,
            'montant_total' => $this->montant_total,
            'taux_couverture_applique' => $this->taux_couverture_applique,
            'montant_assurance' => $this->montant_assurance,
            'montant_patient' => $this->montant_patient,
        ];
    }
}
