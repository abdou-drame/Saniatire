<?php

namespace App\Domain\Maternite\Models;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Patient\Models\Patient;
use App\Domain\Shared\Specialty\SpecialtyRecord;
use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\Structure\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * estimated_delivery_date (DPA) is derived from last_menstrual_period_date
 * (DDR) via Naegele's rule (+280 days) in the saving hook below — same
 * convention as Consultation::$bmi, computed server-side rather than
 * trusted from the payload.
 */
class MaternityRecord extends Model implements SpecialtyRecord
{
    use BelongsToTenant, LogsActivity;

    protected $fillable = [
        'structure_id',
        'site_id',
        'patient_id',
        'consultation_id',
        'last_menstrual_period_date',
        'estimated_delivery_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'last_menstrual_period_date' => 'date',
            'estimated_delivery_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MaternityRecord $record) {
            if ($record->isDirty('last_menstrual_period_date') && $record->last_menstrual_period_date) {
                $record->estimated_delivery_date = $record->last_menstrual_period_date->copy()->addDays(280);
            }
        });
    }

    public static function specialtyType(): string
    {
        return 'maternite';
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

    public function prenatalVisits(): HasMany
    {
        return $this->hasMany(MaternityPrenatalVisit::class);
    }

    public function partogram(): HasOne
    {
        return $this->hasOne(MaternityPartogram::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(MaternityDelivery::class);
    }

    public function postpartumVisits(): HasMany
    {
        return $this->hasMany(MaternityPostpartumVisit::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('maternity_record');
    }
}
