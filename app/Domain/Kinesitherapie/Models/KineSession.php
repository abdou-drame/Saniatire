<?php

namespace App\Domain\Kinesitherapie\Models;

use App\Domain\Shared\Billing\Billable;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class KineSession extends Model implements Billable
{
    use LogsActivity;

    protected $fillable = [
        'kine_program_id',
        'practitioner_id',
        'session_date',
        'exercises_performed',
        'evolution',
        'pain_scale',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(KineProgram::class, 'kine_program_id');
    }

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'practitioner_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('kine_session');
    }

    /**
     * KineSession carries no patient_id of its own — resolved through its
     * parent program.
     */
    public function billingPatientId(): int
    {
        return $this->program->patient_id;
    }

    public function billingCategorie(): string
    {
        return 'kinesitherapie';
    }

    public function billingLibelle(): string
    {
        return "Séance de kinésithérapie #{$this->id}";
    }

    public function billingTariffCode(): string
    {
        return 'KINESITHERAPIE_SEANCE';
    }

    public function billingQuantite(): int
    {
        return 1;
    }
}
