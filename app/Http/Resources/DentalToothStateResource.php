<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DentalToothStateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dental_chart_id' => $this->dental_chart_id,
            'tooth_fdi' => $this->tooth_fdi,
            'status' => $this->status,
            'notes' => $this->notes,
            'updated_at' => $this->updated_at,
        ];
    }
}
