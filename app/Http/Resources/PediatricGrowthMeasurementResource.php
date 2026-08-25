<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PediatricGrowthMeasurementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pediatric_record_id' => $this->pediatric_record_id,
            'measured_at' => $this->measured_at?->format('Y-m-d'),
            'weight_kg' => $this->weight_kg,
            'height_cm' => $this->height_cm,
            'head_circumference_cm' => $this->head_circumference_cm,
        ];
    }
}
