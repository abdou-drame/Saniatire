<?php

namespace App\Domain\Laboratoire\Models;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Patient\Models\Patient;
use App\Domain\Shared\Billing\Billable;
use App\Domain\Shared\Billing\BillingService;
use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\Structure\Models\Site;
use Database\Factories\LabOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LabOrder extends Model implements Billable
{
    /** @use HasFactory<LabOrderFactory> */
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'structure_id',
        'site_id',
        'patient_id',
        'requester_type',
        'requester_id',
        'consultation_id',
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

    public function items(): HasMany
    {
        return $this->hasMany(LabOrderItem::class);
    }

    public function samples(): HasMany
    {
        return $this->hasMany(LabSample::class);
    }

    /**
     * Recomputes the order's global status from its items/results —
     * called after a sample is registered and after every result
     * transition, so lab_orders.status always reflects real progress
     * without the caller having to set it explicitly.
     */
    public function syncStatusFromChildren(): void
    {
        if (in_array($this->status, ['annule'], true)) {
            return;
        }

        $itemsCount = $this->items()->count();
        $transmittedCount = $this->items()
            ->whereHas('result', fn ($q) => $q->where('status', 'transmis'))
            ->count();

        if ($itemsCount > 0 && $transmittedCount === $itemsCount) {
            $wasAlreadyTransmis = $this->status === 'transmis';

            $this->update(['status' => 'transmis']);

            if (! $wasAlreadyTransmis) {
                app(BillingService::class)->recordService($this);
            }

            return;
        }

        if ($this->items()->whereHas('result', fn ($q) => $q->whereIn('status', ['valide', 'transmis']))->exists()) {
            $this->update(['status' => 'resultats_disponibles']);

            return;
        }

        if ($this->items()->whereHas('result')->exists()) {
            $this->update(['status' => 'en_analyse']);

            return;
        }

        if ($this->samples()->exists() && $this->status === 'demande') {
            $this->update(['status' => 'prelevement_effectue']);
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('lab_order');
    }

    public function billingPatientId(): int
    {
        return $this->patient_id;
    }

    public function billingCategorie(): string
    {
        return 'laboratoire';
    }

    public function billingLibelle(): string
    {
        return "Analyses de laboratoire #{$this->id}";
    }

    public function billingTariffCode(): string
    {
        return 'LABORATOIRE_ANALYSE';
    }

    public function billingQuantite(): int
    {
        return 1;
    }
}
