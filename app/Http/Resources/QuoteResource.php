<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'patient_id' => $this->patient_id,
            'insurance_convention_id' => $this->insurance_convention_id,
            'converted_invoice_id' => $this->converted_invoice_id,
            'numero' => $this->numero,
            'date_emission' => $this->date_emission,
            'montant_total' => $this->montant_total,
            'statut' => $this->statut,
            'patient' => $this->whenLoaded('patient', fn () => $this->patient ? [
                'id' => $this->patient->id,
                'first_name' => $this->patient->first_name,
                'last_name' => $this->patient->last_name,
                'patient_number' => $this->patient->patient_number,
            ] : null),
            'site' => $this->whenLoaded('site', fn () => $this->site ? ['id' => $this->site->id, 'name' => $this->site->name] : null),
            'items' => QuoteItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
