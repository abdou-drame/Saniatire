<?php

namespace App\Domain\Ophtalmo\Models;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Patient\Models\Patient;
use App\Domain\Shared\Specialty\SpecialtyRecord;
use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\Structure\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OphtalmoRecord extends Model implements SpecialtyRecord
{
    use BelongsToTenant, LogsActivity;

    protected $fillable = [
        'structure_id',
        'site_id',
        'patient_id',
        'consultation_id',
        'visual_acuity_od_uncorrected',
        'visual_acuity_od_corrected',
        'visual_acuity_og_uncorrected',
        'visual_acuity_og_corrected',
        'intraocular_pressure_od',
        'intraocular_pressure_og',
        'refraction_od_sphere',
        'refraction_od_cylinder',
        'refraction_od_axis',
        'refraction_og_sphere',
        'refraction_og_cylinder',
        'refraction_og_axis',
        'fundus_exam',
        'optical_correction_prescription',
        'examined_at',
    ];

    protected function casts(): array
    {
        return [
            'examined_at' => 'date',
        ];
    }

    public static function specialtyType(): string
    {
        return 'ophtalmo';
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('ophtalmo_record');
    }
}
