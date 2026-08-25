<?php

namespace App\Domain\Patient\Models;

use Database\Factories\PatientMedicalInfoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PatientMedicalInfo extends Model
{
    /** @use HasFactory<PatientMedicalInfoFactory> */
    use HasFactory, LogsActivity;

    protected $table = 'patient_medical_info';

    protected $fillable = [
        'patient_id',
        'blood_group',
        'medical_history',
        'chronic_diseases',
        'current_treatments',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('patient_medical_info');
    }
}
