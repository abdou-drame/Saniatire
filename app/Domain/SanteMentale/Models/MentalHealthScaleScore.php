<?php

namespace App\Domain\SanteMentale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MentalHealthScaleScore extends Model
{
    use LogsActivity;

    protected $fillable = [
        'mental_health_record_id',
        'scale_name',
        'score',
        'scored_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'scored_at' => 'date',
        ];
    }

    public function mentalHealthRecord(): BelongsTo
    {
        return $this->belongsTo(MentalHealthRecord::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('mental_health_scale_score');
    }
}
