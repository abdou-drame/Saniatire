<?php

namespace App\Domain\Hospitalisation\Models;

use App\Domain\Patient\Models\Patient;
use App\Domain\Shared\Billing\Billable;
use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\Structure\Models\Site;
use App\Domain\User\Models\User;
use Database\Factories\HospitalizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Hospitalization extends Model implements Billable
{
    /** @use HasFactory<HospitalizationFactory> */
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'structure_id',
        'site_id',
        'patient_id',
        'bed_id',
        'ward_id',
        'attending_physician_id',
        'admitted_at',
        'admission_reason',
        'discharged_at',
        'discharge_summary',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'admitted_at' => 'datetime',
            'discharged_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function attendingPhysician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attending_physician_id');
    }

    public function dailyNotes(): HasMany
    {
        return $this->hasMany(HospitalizationDailyNote::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('hospitalization');
    }

    public function billingPatientId(): int
    {
        return $this->patient_id;
    }

    public function billingCategorie(): string
    {
        return 'hospitalisation';
    }

    public function billingLibelle(): string
    {
        return "Hospitalisation #{$this->id}";
    }

    public function billingTariffCode(): string
    {
        return 'HOSPITALISATION_NUIT';
    }

    /**
     * Nombre de nuitées, au moins 1 même pour une hospitalisation de moins
     * de 24h.
     */
    public function billingQuantite(): int
    {
        if (! $this->admitted_at || ! $this->discharged_at) {
            return 1;
        }

        return max(1, $this->admitted_at->diffInDays($this->discharged_at));
    }
}
