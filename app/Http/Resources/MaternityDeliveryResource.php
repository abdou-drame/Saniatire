<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaternityDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'maternity_record_id' => $this->maternity_record_id,
            'practitioner_id' => $this->practitioner_id,
            'mode' => $this->mode,
            'delivered_at' => $this->delivered_at,
            'complications' => $this->complications,
            'newborns' => MaternityNewbornResource::collection($this->whenLoaded('newborns')),
            'created_at' => $this->created_at,
        ];
    }
}
