<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PediatricRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'patient_id' => $this->patient_id,
            'consultation_id' => $this->consultation_id,
            'growth_measurements' => PediatricGrowthMeasurementResource::collection($this->whenLoaded('growthMeasurements')),
            'vaccinations' => PediatricVaccinationResource::collection($this->whenLoaded('vaccinations')),
            'development_observations' => PediatricDevelopmentObservationResource::collection($this->whenLoaded('developmentObservations')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
