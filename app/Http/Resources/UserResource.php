<?php

namespace App\Http\Resources;

use App\Domain\Platform\SubscriptionState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'photo_path' => $this->photo_path,
            'is_active' => $this->is_active,
            'must_change_password' => $this->must_change_password,
            'last_login_at' => $this->last_login_at,
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'two_factor_required' => $this->requiresTwoFactor(),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'sites' => $this->whenLoaded('sites', fn () => SiteResource::collection($this->sites)),
            'structure_name' => $this->whenLoaded('structure', fn () => $this->structure?->trade_name ?? $this->structure?->legal_name),
            // Uniquement quand la structure est chargée, c'est-à-dire pour le
            // compte connecté lui-même (login, challenge 2FA, /auth/me) :
            // état calculé par SubscriptionState, affiché tel quel par le frontend.
            'subscription' => $this->whenLoaded('structure', fn () => SubscriptionState::forStructure($this->structure_id)->toArrayFor($this->resource)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
