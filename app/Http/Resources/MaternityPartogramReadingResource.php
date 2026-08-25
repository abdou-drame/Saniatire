<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaternityPartogramReadingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'maternity_partogram_id' => $this->maternity_partogram_id,
            'recorded_at' => $this->recorded_at,
            'cervical_dilation_cm' => $this->cervical_dilation_cm,
            'fetal_heart_rate' => $this->fetal_heart_rate,
            'contractions_per_10min' => $this->contractions_per_10min,
            'notes' => $this->notes,
        ];
    }
}
