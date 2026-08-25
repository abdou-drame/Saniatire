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
            'message' => $this->message,
            'created_at' => $this->created_at,
        ];
    }
}
