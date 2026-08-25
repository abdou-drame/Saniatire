<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PediatricDevelopmentObservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pediatric_record_id' => $this->pediatric_record_id,
            'age_months' => $this->age_months,
            'observation' => $this->observation,
            'observed_at' => $this->observed_at?->format('Y-m-d'),
        ];
    }
}
