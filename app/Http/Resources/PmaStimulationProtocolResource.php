<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PmaStimulationProtocolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pma_record_id' => $this->pma_record_id,
            'protocol_type' => $this->protocol_type,
            'medications' => $this->medications,
            'started_at' => $this->started_at?->format('Y-m-d'),
            'ended_at' => $this->ended_at?->format('Y-m-d'),
        ];
    }
}
