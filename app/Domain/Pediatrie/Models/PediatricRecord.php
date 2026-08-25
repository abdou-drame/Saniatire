<?php

namespace App\Domain\Pediatrie\Models;

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

class PediatricRecord extends Model implements SpecialtyRecord
{
    use BelongsToTenant, LogsActivity;

    protected $fillable = [
        'structure_id',
        'site_id',
        'patient_id',
        'consultation_id',
    ];

    public static function specialtyType(): string
    {
        return 'pediatrie';
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

    public function growthMeasurements(): HasMany
    {
        return $this->hasMany(PediatricGrowthMeasurement::class)->orderBy('measured_at');
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(PediatricVaccination::class)->orderBy('administered_at');
    }

    public function developmentObservations(): HasMany
    {
        return $this->hasMany(PediatricDevelopmentObservation::class)->orderBy('observed_at');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('pediatric_record');
    }
}
