<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OncoResponseEvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'onco_record_id' => $this->onco_record_id,
            'evaluated_at' => $this->evaluated_at?->format('Y-m-d'),
            'response' => $this->response,
            'notes' => $this->notes,
        ];
    }
}
