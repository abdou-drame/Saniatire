<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderReceptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'receptionne_par' => $this->receptionne_par,
            'quantite_recue' => $this->quantite_recue,
            'date_reception' => $this->date_reception,
            'controle_qualite' => $this->controle_qualite,
            'numero_lot' => $this->numero_lot,
            'date_peremption' => $this->date_peremption,
            'created_at' => $this->created_at,
        ];
    }
}
