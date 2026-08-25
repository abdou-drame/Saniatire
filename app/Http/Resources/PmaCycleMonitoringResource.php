<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PmaCycleMonitoringResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pma_record_id' => $this->pma_record_id,
            'monitoring_date' => $this->monitoring_date?->format('Y-m-d'),
            'echo_observations' => $this->echo_observations,
            'hormone_level' => $this->hormone_level,
            'puncture_date' => $this->puncture_date?->format('Y-m-d'),
            'transfer_date' => $this->transfer_date?->format('Y-m-d'),
        ];
    }
}
