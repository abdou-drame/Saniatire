<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabSampleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lab_order_id' => $this->lab_order_id,
            'barcode' => $this->barcode,
            'sample_type' => $this->sample_type,
            'collected_at' => $this->collected_at,
            'collected_by' => $this->collected_by,
            'results' => LabResultResource::collection($this->whenLoaded('results')),
        ];
    }
}
