<?php

namespace App\Http\Resources;

use App\Domain\Platform\ModuleCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    private bool $withModules = false;

    /**
     * Livraison B : uniquement pour le compte connecté lui-même (connexion
     * et /me de son portail) — clés des modules actifs de sa structure,
     * lues telles quelles par le frontend du portail pour masquer ses menus.
     */
    public function withModules(): static
    {
        $this->withModules = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'patient_number' => $this->patient_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'sex' => $this->sex,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'profession' => $this->profession,
            'nationality' => $this->nationality,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_relationship' => $this->emergency_contact_relationship,
            'photo_path' => $this->photo_path,
            'id_document_path' => $this->id_document_path,
            'portal_activated_at' => $this->portal_activated_at,
            'medical_info' => $this->whenLoaded('medicalInfo'),
            'allergies' => $this->whenLoaded('allergies'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'modules' => $this->when($this->withModules, fn () => ModuleCatalog::activeFor($this->structure_id)),
        ];
    }
}
