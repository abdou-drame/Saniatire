<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Livraison D : ligne de la liste du personnel d'une structure vue par
 * l'administrateur de plateforme. Encore plus étroite que
 * PlatformStaffUserResource : identité, rôles (noms Spatie uniquement,
 * jamais les permissions), état du compte et dernière connexion. Les rôles
 * doivent être chargés en amont (with('roles')) pour éviter un N+1.
 */
class PlatformStructureUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'roles' => $this->roles->pluck('name')->values()->all(),
            'is_active' => (bool) $this->is_active,
            'is_locked' => $this->isLocked(),
            'locked_until' => $this->locked_until?->toISOString(),
            'last_login_at' => $this->last_login_at?->toISOString(),
        ];
    }
}
