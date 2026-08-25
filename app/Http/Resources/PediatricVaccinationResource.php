<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PediatricVaccinationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pediatric_record_id' => $this->pediatric_record_id,
            'vaccine_name' => $this->vaccine_name,
            'dose_number' => $this->dose_number,
            'administered_at' => $this->administered_at?->format('Y-m-d'),
        ];
    }
}
