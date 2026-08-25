<?php

namespace App\Domain\Cardiologie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CardioReading extends Model
{
    use LogsActivity;

    protected $fillable = [
        'cardio_record_id',
        'measured_at',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'heart_rate',
        'rhythm',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'datetime',
        ];
    }

    public function cardioRecord(): BelongsTo
    {
        return $this->belongsTo(CardioRecord::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('cardio_reading');
    }
}
