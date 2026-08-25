<?php

namespace App\Domain\Cardiologie\Models;

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

class CardioRecord extends Model implements SpecialtyRecord
{
    use BelongsToTenant, LogsActivity;

    protected $fillable = [
        'structure_id',
        'site_id',
        'patient_id',
        'consultation_id',
        'risk_factors',
        'current_treatment',
        'examined_at',
    ];

    protected function casts(): array
    {
        return [
            'risk_factors' => 'array',
            'examined_at' => 'date',
        ];
    }

    public static function specialtyType(): string
    {
        return 'cardiologie';
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

    public function readings(): HasMany
    {
        return $this->hasMany(CardioReading::class)->orderBy('measured_at');
    }

    public function ecgResults(): HasMany
    {
        return $this->hasMany(CardioEcgResult::class)->orderBy('performed_at');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('cardio_record');
    }
}
