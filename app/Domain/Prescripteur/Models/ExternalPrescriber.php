<?php

namespace App\Domain\Prescripteur\Models;

use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\Structure\Models\Structure;
use Database\Factories\ExternalPrescriberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ExternalPrescriber extends Authenticatable
{
    /** @use HasFactory<ExternalPrescriberFactory> */
    use BelongsToTenant, HasApiTokens, HasFactory, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = [
        'structure_id',
        'nom',
        'specialite',
        'email',
        'telephone',
        'statut',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'portal_activated_at' => 'datetime',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function isActif(): bool
    {
        return $this->statut === 'actif';
    }

    /**
     * Passe par NotificationDispatcher (étape 7a) plutôt que par la
     * notification mail native de Laravel — même patron que Patient.
     */
    public function sendPasswordResetNotification($token): void
    {
        app(\App\Domain\Notification\NotificationDispatcher::class)->send(
            $this,
            'patient_password_reset',
            [
                'patient_nom' => $this->nom,
                'lien_reinitialisation' => config('app.frontend_url', config('app.url'))."/portail-prescripteur/reinitialiser-mot-de-passe?token={$token}&email={$this->email}",
            ],
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('external_prescriber');
    }
}
