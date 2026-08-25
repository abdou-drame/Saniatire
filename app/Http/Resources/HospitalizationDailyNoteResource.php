<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HospitalizationDailyNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hospitalization_id' => $this->hospitalization_id,
            'author_id' => $this->author_id,
            'note_date' => $this->note_date,
            'care_administered' => $this->care_administered,
            'medications_given' => $this->medications_given,
            'procedures_performed' => $this->procedures_performed,
            'observations' => $this->observations,
            'created_at' => $this->created_at,
        ];
    }
}
