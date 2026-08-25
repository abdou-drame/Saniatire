<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentMaintenanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'biomedical_equipment_id' => $this->biomedical_equipment_id,
            'intervenant_user_id' => $this->intervenant_user_id,
            'type' => $this->type,
            'date_prevue' => $this->date_prevue,
            'date_realisee' => $this->date_realisee,
            'intervenant_externe' => $this->intervenant_externe,
            'cout' => $this->cout,
            'description' => $this->description,
            'statut' => $this->statut,
            'equipment' => $this->whenLoaded('equipment', fn () => $this->equipment ? ['id' => $this->equipment->id, 'nom' => $this->equipment->nom] : null),
            'intervenant_label' => $this->whenLoaded('intervenant', fn () => $this->intervenant ? trim("{$this->intervenant->first_name} {$this->intervenant->last_name}") : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
