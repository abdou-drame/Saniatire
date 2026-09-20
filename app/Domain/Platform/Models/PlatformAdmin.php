<?php

namespace App\Domain\Platform\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * L'administrateur de plateforme n'appartient à aucune structure — c'est
 * précisément l'acteur qui opère en dehors du système d'isolation. Guard
 * `platform` dédié (config/auth.php), sur le même patron que Patient et
 * ExternalPrescriber : un jeton Sanctum émis pour ce modèle n'authentifie
 * jamais les routes `auth:sanctum` normales (le guard sanctum vérifie que
 * le tokenable correspond au modèle de son provider), donc aucune route
 * métier existante n'est accessible avec un jeton platform, structurellement
 * — pas seulement par une permission qu'on pourrait oublier de vérifier.
 *
 * Pas de HasRoles/permissions Spatie ici : il n'y a qu'une seule sorte
 * d'action possible pour ce guard (administration plateforme), donc être
 * authentifié via `auth:platform` suffit, comme pour patient/prescriber.
 */
class PlatformAdmin extends Authenticatable
{
    use HasApiTokens, LogsActivity, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('platform_admin');
    }
}
