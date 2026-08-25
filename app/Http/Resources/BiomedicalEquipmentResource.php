<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BiomedicalEquipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'supplier_id' => $this->supplier_id,
            'nom' => $this->nom,
            'categorie' => $this->categorie,
            'numero_serie' => $this->numero_serie,
            'date_acquisition' => $this->date_acquisition,
            'date_fin_garantie' => $this->date_fin_garantie,
            'statut' => $this->statut,
            'maintenances' => EquipmentMaintenanceResource::collection($this->whenLoaded('maintenances')),
            'site' => $this->whenLoaded('site', fn () => $this->site ? ['id' => $this->site->id, 'name' => $this->site->name] : null),
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? ['id' => $this->supplier->id, 'nom' => $this->supplier->nom] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
