<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KineSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kine_program_id' => $this->kine_program_id,
            'practitioner_id' => $this->practitioner_id,
            'session_date' => $this->session_date,
            'exercises_performed' => $this->exercises_performed,
            'evolution' => $this->evolution,
            'pain_scale' => $this->pain_scale,
            'observations' => $this->observations,
            'created_at' => $this->created_at,
        ];
    }
}
