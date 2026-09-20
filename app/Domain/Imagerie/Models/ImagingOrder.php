<?php

namespace App\Domain\Imagerie\Models;

use App\Domain\Appointment\Models\Appointment;
use App\Domain\Consultation\Models\Consultation;
use App\Domain\Patient\Models\Patient;
use App\Domain\Shared\Billing\Billable;
use App\Domain\Shared\Billing\BillingService;
use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\Structure\Models\Site;
use Database\Factories\ImagingOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ImagingOrder extends Model implements Billable
{
    /** @use HasFactory<ImagingOrderFactory> */
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'structure_id',
        'site_id',
        'patient_id',
        'requester_type',
        'requester_id',
        'consultation_id',
        'appointment_id',
        'exam_type',
        'status',
        'billing_status',
        'ordered_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Étape 7b : polymorphe — soit un User (praticien interne), soit un
     * ExternalPrescriber (prescripteur externe, portail prescripteur).
     */
    public function requester(): MorphTo
    {
        return $this->morphTo();
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function studies(): HasMany
    {
        return $this->hasMany(ImagingStudy::class);
    }

    /**
     * Recomputes the order's global status from its studies, mirroring
     * LabOrder::syncStatusFromChildren — called after a study is created
     * and after every report/study transition.
     */
    public function syncStatusFromChildren(): void
    {
        if (in_array($this->status, ['annule'], true)) {
            return;
        }

        $studiesCount = $this->studies()->count();

        if ($studiesCount === 0) {
            return;
        }

        $transmittedCount = $this->studies()->where('status', 'transmis')->count();
        if ($transmittedCount === $studiesCount) {
            $wasAlreadyTransmis = $this->status === 'transmis';

            $this->update(['status' => 'transmis']);

            if (! $wasAlreadyTransmis) {
                app(BillingService::class)->recordService($this);
            }

            return;
        }

        if ($this->studies()->where('status', 'valide')->exists()) {
            $this->update(['status' => 'valide']);

            return;
        }

        if ($this->studies()->where('status', 'cr_redige')->exists()) {
            $this->update(['status' => 'cr_redige']);

            return;
        }

        if ($this->studies()->where('status', 'en_interpretation')->exists()) {
            $this->update(['status' => 'en_interpretation']);

            return;
        }

        if ($this->studies()->where('status', 'realise')->exists()) {
            $this->update(['status' => 'realise']);
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('imaging_order');
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
        return 'imagerie';
    }

    public function billingLibelle(): string
    {
        return "Examen d'imagerie #{$this->id} ({$this->exam_type})";
    }

    public function billingTariffCode(): string
    {
        return 'IMAGERIE_'.strtoupper((string) $this->exam_type);
    }

    public function billingQuantite(): int
    {
        return 1;
    }
}
