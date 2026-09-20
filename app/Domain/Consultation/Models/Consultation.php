<?php

namespace App\Domain\Consultation\Models;

use App\Domain\Appointment\Models\Appointment;
use App\Domain\Patient\Models\Patient;
use App\Domain\Shared\Billing\Billable;
use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\Structure\Models\Site;
use App\Domain\User\Models\User;
use Database\Factories\ConsultationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Vitals extensibility (cahier des charges §18): the standard clinical
 * parameters (weight, height, BMI, temperature, blood pressure, heart
 * rate, respiratory rate, spo2, glycemia, pain scale) are fixed, typed
 * columns — they're recorded on every consultation regardless of
 * specialty, so they benefit from real typing, validation and querying.
 * `extra_vitals` is a single JSON column for specialty-specific
 * parameters (e.g. a cardiology or pediatric growth-curve value) that
 * don't warrant their own column and vary by specialty — new ones can be
 * added there without a migration. If a future specialty module needs to
 * query its extra parameters directly (not just display them), promote
 * that specific key to a real column at that point; don't build a generic
 * EAV table pre-emptively for parameters that don't exist yet.
 */
class Consultation extends Model implements Billable
{
    /** @use HasFactory<ConsultationFactory> */
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'structure_id',
        'patient_id',
        'practitioner_id',
        'site_id',
        'appointment_id',
        'specialty_type',
        'reason',
        'history_of_illness',
        'weight_kg',
        'height_cm',
        'bmi',
        'temperature_c',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'heart_rate',
        'respiratory_rate',
        'spo2',
        'glycemia',
        'pain_scale',
        'extra_vitals',
        'clinical_exam',
        'recommendations',
        'referral',
        'follow_up_suggested_at',
        'status',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
            'height_cm' => 'decimal:1',
            'bmi' => 'decimal:2',
            'temperature_c' => 'decimal:1',
            'glycemia' => 'decimal:1',
            'extra_vitals' => 'array',
            'follow_up_suggested_at' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Consultation $consultation) {
            if ($consultation->weight_kg && $consultation->height_cm) {
                $heightMeters = $consultation->height_cm / 100;
                $consultation->bmi = round($consultation->weight_kg / ($heightMeters ** 2), 2);
            }
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'practitioner_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(ConsultationDiagnosis::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('consultation');
    }

    public function billingPatientId(): int
    {
        return $this->patient_id;
    }

    public function billingStructureId(): int
    {
        return $this->structure_id;
    }

    public function billingCategorie(): string
    {
        return 'consultation';
    }

    public function billingLibelle(): string
    {
        return 'Consultation';
    }

    public function billingTariffCode(): string
    {
        return 'CONSULTATION_GENERALE';
    }

    public function billingQuantite(): int
    {
        return 1;
    }
}
