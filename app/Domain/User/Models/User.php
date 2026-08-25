<?php

namespace App\Domain\User\Models;

use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\Structure\Models\Site;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    protected $guard_name = 'sanctum';

    protected $fillable = [
        'structure_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'photo_path',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Rôles pour lesquels la 2FA est obligatoire (étape 9 §2) : le niveau
     * d'accès le plus large (administrateur, direction, directeur_medical)
     * et les seuls rôles cliniques atteignant les modules pma et
     * sante_mentale (voir RolePermissionSeeder — specialiste_pma,
     * psychiatre, psychologue sont les seuls rôles, avec directeur_medical,
     * à porter ces permissions). Centralisé ici plutôt que dupliqué entre
     * le middleware et les tests.
     */
    public const ROLES_REQUIRING_TWO_FACTOR = [
        'administrateur',
        'direction',
        'directeur_medical',
        'specialiste_pma',
        'psychiatre',
        'psychologue',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function requiresTwoFactor(): bool
    {
        return $this->hasAnyRole(self::ROLES_REQUIRING_TWO_FACTOR);
    }

    /**
     * Démarre (ou redémarre) l'enrôlement : un secret non confirmé est
     * remplacé sans confirmation implicite — confirmTwoFactor() doit être
     * appelé explicitement avec un code valide pour activer la 2FA.
     */
    public function generateTwoFactorSecret(): string
    {
        $secret = app(Google2FA::class)->generateSecretKey();

        $this->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        return $secret;
    }

    public function verifyTwoFactorCode(string $code): bool
    {
        if (! $this->two_factor_secret) {
            return false;
        }

        return app(Google2FA::class)->verifyKey($this->two_factor_secret, $code) !== false;
    }

    /**
     * @return array<int,string> codes de récupération en clair — à
     *                            afficher une seule fois à l'utilisateur, jamais restitués ensuite.
     */
    public function confirmTwoFactor(): array
    {
        $codes = collect(range(1, 8))->map(fn () => Str::upper(Str::random(10)))->all();

        $this->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $codes,
        ])->save();

        return $codes;
    }

    public function disableTwoFactor(): void
    {
        $this->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /**
     * Consomme un code de récupération à usage unique. Retourne false sans
     * effet de bord si le code est invalide ou déjà utilisé.
     */
    public function consumeRecoveryCode(string $code): bool
    {
        $codes = $this->two_factor_recovery_codes ?? [];
        $normalized = Str::upper(trim($code));

        if (! in_array($normalized, $codes, true)) {
            return false;
        }

        $this->forceFill([
            'two_factor_recovery_codes' => array_values(array_diff($codes, [$normalized])),
        ])->save();

        return true;
    }

    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'user_site');
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('user');
    }
}
