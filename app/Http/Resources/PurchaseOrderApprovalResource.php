<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderApprovalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'approval_rule_id' => $this->approval_rule_id,
            'level' => $this->level,
            'min_amount' => $this->min_amount,
            'role_name' => $this->role_name,
            'statut' => $this->statut,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
        ];
    }
}
