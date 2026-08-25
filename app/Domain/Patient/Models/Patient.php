<?php

namespace App\Domain\Patient\Models;

use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\Structure\Models\Structure;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Patient extends Authenticatable
{
    /** @use HasFactory<PatientFactory> */
    use BelongsToTenant, HasApiTokens, HasFactory, LogsActivity, Notifiable, SoftDeletes;

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $fillable = [
        'structure_id',
        'patient_number',
        'first_name',
        'last_name',
        'sex',
        'birth_date',
        'phone',
        'email',
        'address',
        'profession',
        'nationality',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'photo_path',
        'id_document_path',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'password' => 'hashed',
            'portal_activated_at' => 'datetime',
        ];
    }

    /**
     * Passe par le système de notifications multicanal (étape 7a) plutôt
     * que par la notification mail native de Laravel, pour rester cohérent
     * avec le principe "tout passe par NotificationDispatcher".
     */
    public function sendPasswordResetNotification($token): void
    {
        app(\App\Domain\Notification\NotificationDispatcher::class)->send(
            $this,
            'patient_password_reset',
            [
                'patient_nom' => trim("{$this->first_name} {$this->last_name}"),
                'lien_reinitialisation' => config('app.frontend_url', config('app.url'))."/portail-patient/reinitialiser-mot-de-passe?token={$token}&email={$this->email}",
            ],
        );
    }

    protected static function booted(): void
    {
        static::creating(function (Patient $patient) {
            if (! $patient->patient_number) {
                $patient->patient_number = static::generatePatientNumber($patient->structure_id);
            }
        });
    }

    /**
     * Format: PT-{structure_id}-{year}-{sequence sur 6 chiffres}, la
     * séquence étant remise à zéro chaque année pour chaque structure.
     */
    public static function generatePatientNumber(?int $structureId): string
    {
        $year = now()->format('Y');
        $prefix = sprintf('PT-%04d-%s-', $structureId ?? 0, $year);

        $count = static::withoutGlobalScopes()
            ->where('structure_id', $structureId)
            ->where('patient_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }

    public function medicalInfo(): HasOne
    {
        return $this->hasOne(PatientMedicalInfo::class);
    }

    public function allergies(): HasMany
    {
        return $this->hasMany(PatientAllergy::class);
    }

    public function structureAccesses(): HasMany
    {
        return $this->hasMany(PatientStructureAccess::class);
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(\App\Domain\Consultation\Models\Consultation::class);
    }

    public function sharedStructures(): BelongsToMany
    {
        return $this->belongsToMany(Structure::class, 'patient_structure_access')
            ->withPivot(['granted_by', 'granted_at', 'revoked_at'])
            ->withTimestamps();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('patient');
    }
}
