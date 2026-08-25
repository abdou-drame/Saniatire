<?php

namespace App\Domain\Dialyse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DialysisSessionVital extends Model
{
    use LogsActivity;

    protected $fillable = [
        'dialysis_session_id',
        'measured_at',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'heart_rate',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(DialysisSession::class, 'dialysis_session_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('dialysis_session_vital');
    }
}
