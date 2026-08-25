<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoincCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'label' => $this->label,
            'component' => $this->component,
            'default_unit' => $this->default_unit,
            'version' => $this->version,
            'status' => $this->status,
        ];
    }
}
