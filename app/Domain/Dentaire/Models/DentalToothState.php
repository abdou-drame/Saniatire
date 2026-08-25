<?php

namespace App\Domain\Dentaire\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DentalToothState extends Model
{
    use LogsActivity;

    protected $fillable = [
        'dental_chart_id',
        'tooth_fdi',
        'status',
        'notes',
    ];

    public function dentalChart(): BelongsTo
    {
        return $this->belongsTo(DentalChart::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('dental_tooth_state');
    }
}
