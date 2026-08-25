<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IcdCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'version' => $this->version,
            'label' => $this->label,
            'parent_id' => $this->parent_id,
            'level' => $this->level,
            'status' => $this->status,
        ];
    }
}
