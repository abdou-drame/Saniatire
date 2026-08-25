<?php

namespace App\Domain\Assurance\Models;

use App\Domain\Shared\Tenancy\BelongsToTenant;
use Database\Factories\InsuranceConventionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InsuranceConvention extends Model
{
    /** @use HasFactory<InsuranceConventionFactory> */
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'structure_id',
        'insurance_provider_id',
        'nom',
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

    public function provider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class, 'insurance_provider_id');
    }

    public function coverageRules(): HasMany
    {
        return $this->hasMany(InsuranceConventionCoverageRule::class);
    }

    public function patientCoverages(): HasMany
    {
        return $this->hasMany(PatientInsuranceCoverage::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('insurance_convention');
    }
}
