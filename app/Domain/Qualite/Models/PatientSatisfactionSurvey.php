<?php

namespace App\Domain\Qualite\Models;

use App\Domain\Patient\Models\Patient;
use App\Domain\Shared\Tenancy\BelongsToTenant;
use Database\Factories\PatientSatisfactionSurveyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PatientSatisfactionSurvey extends Model
{
    /** @use HasFactory<PatientSatisfactionSurveyFactory> */
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'structure_id',
        'patient_id',
        'prestation_type',
        'prestation_id',
        'service',
        'note',
        'commentaire',
        'date',
    ];

    protected function casts(): array
    {
        return [
            'note' => 'integer',
            'date' => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function prestation(): MorphTo
    {
        return $this->morphTo();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('patient_satisfaction_survey');
    }
}
