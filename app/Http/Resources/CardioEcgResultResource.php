<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CardioEcgResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cardio_record_id' => $this->cardio_record_id,
            'performed_at' => $this->performed_at,
            'rhythm' => $this->rhythm,
            'heart_rate' => $this->heart_rate,
            'anomalies' => $this->anomalies,
            'tracing_file_reference' => $this->tracing_file_reference,
        ];
    }
}
