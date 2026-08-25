<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockThresholdResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'product_id' => $this->product_id,
            'site_id' => $this->site_id,
            'seuil_minimum' => $this->seuil_minimum,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
