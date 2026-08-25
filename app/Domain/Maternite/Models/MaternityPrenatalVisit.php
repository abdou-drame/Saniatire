<?php

namespace App\Domain\Maternite\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MaternityPrenatalVisit extends Model
{
    use LogsActivity;

    protected $fillable = [
        'maternity_record_id',
        'practitioner_id',
        'visit_number',
        'gestational_age_weeks',
        'weight_kg',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'fundal_height_cm',
        'fetal_movements',
        'fetal_heart_rate',
        'visit_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
            'fundal_height_cm' => 'decimal:1',
            'visit_date' => 'date',
        ];
    }

    public function maternityRecord(): BelongsTo
    {
        return $this->belongsTo(MaternityRecord::class);
    }

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'practitioner_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('maternity_prenatal_visit');
    }
}
