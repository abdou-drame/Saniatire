<?php

namespace App\Domain\Shared\Auth;

use App\Domain\Shared\Auth\Events\PortailActivationDemandee;
use App\Domain\Shared\Auth\Models\PortalActivation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PortalActivationService
{
    public function createFor(Model $activatable): string
    {
        $token = Str::random(64);

        PortalActivation::create([
            'activatable_type' => $activatable::class,
            'activatable_id' => $activatable->getKey(),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours(48),
        ]);

        PortailActivationDemandee::dispatch($activatable, $token);

        return $token;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function activate(string $modelClass, string $token, string $password): Model
    {
        $activation = PortalActivation::where('activatable_type', $modelClass)
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $activatable = $modelClass::withoutGlobalScopes()->findOrFail($activation->activatable_id);

        abort_if($activatable->portal_activated_at !== null, 422, 'Ce compte est déjà activé.');

        // Contrôle applicatif d'unicité d'email parmi les comptes déjà
        // activés : email n'est ni unique ni NOT NULL en base pour les
        // patients, une contrainte DB casserait les enregistrements
        // existants.
        if ($activatable->email) {
            $emailDejaActive = $modelClass::withoutGlobalScopes()
                ->where('email', $activatable->email)
                ->whereKeyNot($activatable->getKey())
                ->whereNotNull('portal_activated_at')
                ->exists();

            abort_if($emailDejaActive, 422, 'Un autre compte est déjà activé avec cet email.');
        }

        $activatable->forceFill([
            'password' => Hash::make($password),
            'portal_activated_at' => now(),
        ])->save();

        $activation->update(['used_at' => now()]);

        return $activatable;
    }
}
