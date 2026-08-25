<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lab_order_id' => $this->lab_order_id,
            'status' => $this->status,
            'loinc_code' => new LoincCodeResource($this->whenLoaded('loincCode')),
            'result' => new LabResultResource($this->whenLoaded('result')),
        ];
    }
}
