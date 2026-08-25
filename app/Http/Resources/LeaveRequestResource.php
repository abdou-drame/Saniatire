<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'date_debut' => $this->date_debut?->toDateString(),
            'date_fin' => $this->date_fin?->toDateString(),
            'statut' => $this->statut,
            'validated_by' => $this->validated_by,
            'commentaire' => $this->commentaire,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
