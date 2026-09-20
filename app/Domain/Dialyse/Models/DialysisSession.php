<?php

namespace App\Domain\Dialyse\Models;

use App\Domain\Shared\Billing\Billable;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DialysisSession extends Model implements Billable
{
    use LogsActivity;

    protected $fillable = [
        'dialysis_program_id',
        'practitioner_id',
        'session_date',
        'pre_weight_kg',
        'post_weight_kg',
        'dry_weight_kg',
        'duration_minutes',
        'blood_flow_rate_ml_min',
        'ultrafiltration_volume_ml',
        'complications',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'datetime',
            'pre_weight_kg' => 'decimal:2',
            'post_weight_kg' => 'decimal:2',
            'dry_weight_kg' => 'decimal:2',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(DialysisProgram::class, 'dialysis_program_id');
    }

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'practitioner_id');
    }

    public function vitals(): HasMany
    {
        return $this->hasMany(DialysisSessionVital::class)->orderBy('measured_at');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('dialysis_session');
    }

    /**
     * DialysisSession carries no patient_id of its own — resolved through
     * its parent program, same as the tenant-scoping check already done in
     * DialysisProgramController::storeSessionVital().
     */
    public function billingPatientId(): int
    {
        return $this->program->patient_id;
    }

    public function billingStructureId(): int
    {
        return $this->program->structure_id;
    }

    public function billingCategorie(): string
    {
        return 'dialyse';
    }

    public function billingLibelle(): string
    {
        return "Séance de dialyse #{$this->id}";
    }

    public function billingTariffCode(): string
    {
        return 'DIALYSE_SEANCE';
    }

    public function billingQuantite(): int
    {
        return 1;
    }
}
