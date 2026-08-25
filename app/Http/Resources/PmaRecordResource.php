<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PmaRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'patient_id' => $this->patient_id,
            'consultation_id' => $this->consultation_id,
            'fertility_history' => $this->fertility_history,
            'exams_performed' => $this->exams_performed,
            'attempt_result' => $this->attempt_result,
            'stimulation_protocols' => PmaStimulationProtocolResource::collection($this->whenLoaded('stimulationProtocols')),
            'cycle_monitorings' => PmaCycleMonitoringResource::collection($this->whenLoaded('cycleMonitorings')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
