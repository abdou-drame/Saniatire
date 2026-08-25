<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StructureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'legal_name' => $this->legal_name,
            'trade_name' => $this->trade_name,
            'type' => $this->type,
            'logo_path' => $this->logo_path,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'phone' => $this->phone,
            'email' => $this->email,
            'opening_hours' => $this->opening_hours,
            'registration_number' => $this->registration_number,
            'tax_number' => $this->tax_number,
            'color_primary' => $this->color_primary,
            'color_secondary' => $this->color_secondary,
            'currency' => $this->currency,
            'locale' => $this->locale,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
