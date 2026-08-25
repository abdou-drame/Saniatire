<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_request_id' => $this->purchase_request_id,
            'product_id' => $this->product_id,
            'quantite_commandee' => $this->quantite_commandee,
            'prix_unitaire' => $this->prix_unitaire,
            'quantite_recue' => $this->quantite_recue,
        ];
    }
}
