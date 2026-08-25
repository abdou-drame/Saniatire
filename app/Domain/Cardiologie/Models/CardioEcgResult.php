<?php

namespace App\Domain\Cardiologie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CardioEcgResult extends Model
{
    use LogsActivity;

    protected $fillable = [
        'cardio_record_id',
        'performed_at',
        'rhythm',
        'heart_rate',
        'anomalies',
        'tracing_file_reference',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
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
            ->useLogName('cardio_ecg_result');
    }
}
