<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DentalProcedureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dental_chart_id' => $this->dental_chart_id,
            'consultation_id' => $this->consultation_id,
            'practitioner_id' => $this->practitioner_id,
            'tooth_fdi' => $this->tooth_fdi,
            'act_type' => $this->act_type,
            'performed_at' => $this->performed_at?->format('Y-m-d'),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
