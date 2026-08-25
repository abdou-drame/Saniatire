<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaternityRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'patient_id' => $this->patient_id,
            'consultation_id' => $this->consultation_id,
            'last_menstrual_period_date' => $this->last_menstrual_period_date?->format('Y-m-d'),
            'estimated_delivery_date' => $this->estimated_delivery_date?->format('Y-m-d'),
            'status' => $this->status,
            'prenatal_visits' => MaternityPrenatalVisitResource::collection($this->whenLoaded('prenatalVisits')),
            'partogram' => new MaternityPartogramResource($this->whenLoaded('partogram')),
            'delivery' => new MaternityDeliveryResource($this->whenLoaded('delivery')),
            'postpartum_visits' => MaternityPostpartumVisitResource::collection($this->whenLoaded('postpartumVisits')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
