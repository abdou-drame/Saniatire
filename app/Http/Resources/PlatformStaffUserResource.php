<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Livraison C : vue d'un compte du personnel (modèle User) pour
 * l'administrateur de plateforme. Volontairement plus étroite que
 * UserResource : ni permissions, ni sites, ni abonnement/modules (calculés
 * pour le compte connecté lui-même), mais l'état de verrouillage dont la
 * plateforme a besoin pour débloquer un compte. Jamais de mot de passe ni
 * de secret 2FA (déjà dans $hidden du modèle).
 */
class PlatformStaffUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'is_active' => (bool) $this->is_active,
            'must_change_password' => (bool) $this->must_change_password,
            'is_locked' => $this->isLocked(),
            'locked_until' => $this->locked_until?->toISOString(),
            'failed_login_attempts' => (int) $this->failed_login_attempts,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
