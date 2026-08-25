<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MentalHealthScaleScoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mental_health_record_id' => $this->mental_health_record_id,
            'scale_name' => $this->scale_name,
            'score' => $this->score,
            'scored_at' => $this->scored_at?->format('Y-m-d'),
        ];
    }
}
