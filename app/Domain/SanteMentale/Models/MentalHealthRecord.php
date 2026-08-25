<?php

namespace App\Domain\SanteMentale\Models;

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

class MentalHealthRecord extends Model implements SpecialtyRecord
{
    use BelongsToTenant, LogsActivity;

    protected $fillable = [
        'structure_id',
        'site_id',
        'patient_id',
        'consultation_id',
        'consultation_reason',
        'clinical_evaluation',
        'ongoing_treatment',
    ];

    /**
     * Étape 9 §2 : chiffrement applicatif (Laravel Crypt, au-delà du
     * chiffrement au niveau base) pour les champs cliniques en texte libre
     * les plus sensibles de ce module. logExcept() ci-dessous est le
     * complément indispensable : sans lui, LogsActivity écrirait quand
     * même les valeurs en clair dans activity_log.properties à chaque
     * modification, contournant le chiffrement.
     */
    protected function casts(): array
    {
        return [
            'consultation_reason' => 'encrypted',
            'clinical_evaluation' => 'encrypted',
            'ongoing_treatment' => 'encrypted',
        ];
    }

    public static function specialtyType(): string
    {
        return 'sante_mentale';
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

    public function scaleScores(): HasMany
    {
        return $this->hasMany(MentalHealthScaleScore::class)->orderBy('scored_at');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->logExcept(['consultation_reason', 'clinical_evaluation', 'ongoing_treatment'])
            ->useLogName('mental_health_record');
    }
}
