<?php

namespace App\Domain\Pma\Models;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Patient\Models\Patient;
use App\Domain\Shared\Specialty\SpecialtyRecord;
use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\Structure\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PmaRecord extends Model implements SpecialtyRecord
{
    use BelongsToTenant, LogsActivity;

    protected $fillable = [
        'structure_id',
        'site_id',
        'patient_id',
        'consultation_id',
        'fertility_history',
        'exams_performed',
        'attempt_result',
    ];

    /**
     * Étape 9 §2 : voir MentalHealthRecord pour la justification du
     * chiffrement applicatif + logExcept(). attempt_result n'est pas
     * chiffré : c'est un enum contraint en base (en_cours/positif/negatif),
     * incompatible avec la valeur opaque produite par le cast encrypted.
     */
    protected function casts(): array
    {
        return [
            'fertility_history' => 'encrypted',
            'exams_performed' => 'encrypted',
        ];
    }

    public static function specialtyType(): string
    {
        return 'pma';
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function stimulationProtocols(): HasMany
    {
        return $this->hasMany(PmaStimulationProtocol::class);
    }

    public function cycleMonitorings(): HasMany
    {
        return $this->hasMany(PmaCycleMonitoring::class)->orderBy('monitoring_date');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->logExcept(['fertility_history', 'exams_performed'])
            ->useLogName('pma_record');
    }
}
