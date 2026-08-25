<?php

namespace App\Domain\Pediatrie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PediatricGrowthMeasurement extends Model
{
    use LogsActivity;

    protected $fillable = [
        'pediatric_record_id',
        'measured_at',
        'weight_kg',
        'height_cm',
        'head_circumference_cm',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'date',
        ];
    }

    public function pediatricRecord(): BelongsTo
    {
        return $this->belongsTo(PediatricRecord::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('pediatric_growth_measurement');
    }
}
