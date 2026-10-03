<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'monthly_price_fcfa' => $this->monthly_price_fcfa,
            'annual_price_fcfa' => $this->annual_price_fcfa,
            'is_active' => $this->is_active,
        ];
    }
}
