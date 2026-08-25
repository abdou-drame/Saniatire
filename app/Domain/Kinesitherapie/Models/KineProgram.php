<?php

namespace App\Domain\Kinesitherapie\Models;

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

class KineProgram extends Model implements SpecialtyRecord
{
    use BelongsToTenant, LogsActivity;

    protected $fillable = [
        'structure_id',
        'site_id',
        'patient_id',
        'consultation_id',
        'affected_area',
        'initial_range_of_motion',
        'initial_pain_scale',
        'objectives',
        'status',
        'started_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
        ];
    }

    public static function specialtyType(): string
    {
        return 'kinesitherapie';
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

    public function sessions(): HasMany
    {
        return $this->hasMany(KineSession::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('kine_program');
    }
}
