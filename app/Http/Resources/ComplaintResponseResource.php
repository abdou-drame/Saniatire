<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'auteur_id' => $this->auteur_id,
            'auteur_label' => $this->whenLoaded('auteur', fn () => $this->auteur ? trim("{$this->auteur->first_name} {$this->auteur->last_name}") : null),
            'auteur_role' => $this->whenLoaded('auteur', fn () => $this->auteur?->getRoleNames()->first()),
            'message' => $this->message,
            'visible_patient' => $this->visible_patient,
            'created_at' => $this->created_at,
        ];
    }
}
