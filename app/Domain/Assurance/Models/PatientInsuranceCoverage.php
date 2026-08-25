<?php

namespace App\Domain\Assurance\Models;

use App\Domain\Patient\Models\Patient;
use App\Domain\Shared\Tenancy\BelongsToTenant;
use Database\Factories\PatientInsuranceCoverageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PatientInsuranceCoverage extends Model
{
    /** @use HasFactory<PatientInsuranceCoverageFactory> */
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'structure_id',
        'patient_id',
        'insurance_convention_id',
        'numero_adherent',
        'beneficiaire_type',
        'date_debut',
        'date_fin',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'actif' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function convention(): BelongsTo
    {
        return $this->belongsTo(InsuranceConvention::class, 'insurance_convention_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('patient_insurance_coverage');
    }
}
