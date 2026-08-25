<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'invoice_id' => $this->invoice_id,
            'cash_session_id' => $this->cash_session_id,
            'caissier_id' => $this->caissier_id,
            'mode_paiement' => $this->mode_paiement,
            'reference_transaction' => $this->reference_transaction,
            'statut_mobile_money' => $this->statut_mobile_money,
            'montant' => $this->montant,
            'numero_recu' => $this->numero_recu,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
        ];
    }
}
