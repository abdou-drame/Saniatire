<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaternityPartogramResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'maternity_record_id' => $this->maternity_record_id,
            'labor_started_at' => $this->labor_started_at,
            'status' => $this->status,
            'readings' => MaternityPartogramReadingResource::collection($this->whenLoaded('readings')),
            'created_at' => $this->created_at,
        ];
    }
}
