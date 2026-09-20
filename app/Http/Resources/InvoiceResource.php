<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'patient_id' => $this->patient_id,
            'insurance_convention_id' => $this->insurance_convention_id,
            'numero' => $this->numero,
            'date_emission' => $this->date_emission,
            'date_echeance' => $this->date_echeance,
            'montant_total' => $this->montant_total,
            'montant_part_patient' => $this->montant_part_patient,
            'montant_part_assurance' => $this->montant_part_assurance,
            'statut' => $this->statut,
            'patient' => $this->whenLoaded('patient', fn () => $this->patient ? [
                'id' => $this->patient->id,
                'first_name' => $this->patient->first_name,
                'last_name' => $this->patient->last_name,
                'patient_number' => $this->patient->patient_number,
            ] : null),
            'site' => $this->whenLoaded('site', fn () => $this->site ? ['id' => $this->site->id, 'name' => $this->site->name] : null),
            'insurance_convention_label' => $this->whenLoaded('insuranceConvention', fn () => $this->insuranceConvention?->nom),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
