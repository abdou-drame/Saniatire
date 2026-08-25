<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'supplier_id' => $this->supplier_id,
            'created_by' => $this->created_by,
            'montant_total' => $this->montant_total,
            'statut' => $this->statut,
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'approvals' => PurchaseOrderApprovalResource::collection($this->whenLoaded('approvals')),
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? ['id' => $this->supplier->id, 'nom' => $this->supplier->nom] : null),
            'site' => $this->whenLoaded('site', fn () => $this->site ? ['id' => $this->site->id, 'name' => $this->site->name] : null),
            'created_by_label' => $this->whenLoaded('createdBy', fn () => $this->createdBy ? trim("{$this->createdBy->first_name} {$this->createdBy->last_name}") : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
