<?php

namespace App\Domain\Structure\Models;

use App\Domain\Patient\Models\Patient;
use App\Domain\User\Models\User;
use Database\Factories\StructureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Structure extends Model
{
    /** @use HasFactory<StructureFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'code',
        'legal_name',
        'trade_name',
        'type',
        'logo_path',
        'address',
        'city',
        'country',
        'phone',
        'email',
        'opening_hours',
        'registration_number',
        'tax_number',
        'color_primary',
        'color_secondary',
        'currency',
        'locale',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Raison pour laquelle les comptes rattachés à cette structure (personnel,
     * patients, prescripteurs) ne peuvent pas accéder à l'application, ou null
     * si l'accès est permis. Règle unique, appliquée à chaque émission de
     * token (les 3 logins + le challenge 2FA) et à chaque requête authentifiée
     * (EnsureTenantContext) — une suspension coupe donc aussi les tokens déjà
     * émis. withTrashed() : une structure archivée doit être reconnue comme
     * telle, pas confondue avec une structure inexistante.
     */
    public static function accessDenialReason(?int $structureId): ?string
    {
        $structure = $structureId ? static::withTrashed()->find($structureId) : null;

        if (! $structure || $structure->trashed()) {
            return "Cette structure n'est plus active sur la plateforme.";
        }

        if (! $structure->is_active) {
            return "L'accès à cette structure est suspendu. Contactez l'administration de la plateforme.";
        }

        return null;
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(StructureModule::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('structure');
    }
}
