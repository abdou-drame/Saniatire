<?php

namespace App\Http\Resources;

use App\Domain\Platform\ModuleCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExternalPrescriberResource extends JsonResource
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
            'nom' => $this->nom,
            'specialite' => $this->specialite,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'statut' => $this->statut,
            'portal_activated_at' => $this->portal_activated_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'modules' => $this->when($this->withModules, fn () => ModuleCatalog::activeFor($this->structure_id)),
        ];
    }
}
