<?php

namespace App\Domain\Maternite\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MaternityPartogramReading extends Model
{
    use LogsActivity;

    protected $fillable = [
        'maternity_partogram_id',
        'recorded_at',
        'cervical_dilation_cm',
        'fetal_heart_rate',
        'contractions_per_10min',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'cervical_dilation_cm' => 'decimal:1',
        ];
    }

    public function partogram(): BelongsTo
    {
        return $this->belongsTo(MaternityPartogram::class, 'maternity_partogram_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('maternity_partogram_reading');
    }
}
