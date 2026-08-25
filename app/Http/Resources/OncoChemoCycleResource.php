<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OncoChemoCycleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'onco_record_id' => $this->onco_record_id,
            'cycle_number' => $this->cycle_number,
            'cycle_date' => $this->cycle_date?->format('Y-m-d'),
            'medications' => $this->medications,
            'side_effects' => $this->side_effects,
        ];
    }
}
