<?php

namespace App\Http\Resources;

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
            'last_login_at' => $this->last_login_at,
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'two_factor_required' => $this->requiresTwoFactor(),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'sites' => $this->whenLoaded('sites', fn () => SiteResource::collection($this->sites)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
