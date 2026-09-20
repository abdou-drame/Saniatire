<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StructureModuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'module' => $this->module,
            'is_active' => $this->is_active,
            'activated_at' => $this->activated_at,
            'deactivated_at' => $this->deactivated_at,
        ];
    }
}
