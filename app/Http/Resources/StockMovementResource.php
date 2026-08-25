<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'product_batch_id' => $this->product_batch_id,
            'site_id' => $this->site_id,
            'destination_site_id' => $this->destination_site_id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'quantite' => $this->quantite,
            'motif' => $this->motif,
            'dispensed_for_type' => $this->dispensed_for_type,
            'dispensed_for_id' => $this->dispensed_for_id,
            'product_batch' => $this->whenLoaded('productBatch', fn () => $this->productBatch ? [
                'id' => $this->productBatch->id,
                'numero_lot' => $this->productBatch->numero_lot,
                'product' => $this->productBatch->relationLoaded('product') && $this->productBatch->product
                    ? ['id' => $this->productBatch->product->id, 'nom_commercial' => $this->productBatch->product->nom_commercial]
                    : null,
            ] : null),
            'site' => $this->whenLoaded('site', fn () => $this->site ? ['id' => $this->site->id, 'name' => $this->site->name] : null),
            'destination_site' => $this->whenLoaded('destinationSite', fn () => $this->destinationSite ? ['id' => $this->destinationSite->id, 'name' => $this->destinationSite->name] : null),
            'user_label' => $this->whenLoaded('user', fn () => $this->user ? trim("{$this->user->first_name} {$this->user->last_name}") : null),
            'created_at' => $this->created_at,
        ];
    }
}
