<?php

namespace App\Domain\Pediatrie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PediatricDevelopmentObservation extends Model
{
    use LogsActivity;

    protected $fillable = [
        'pediatric_record_id',
        'age_months',
        'observation',
        'observed_at',
    ];

    protected function casts(): array
    {
        return [
            'observed_at' => 'date',
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
            ->useLogName('pediatric_development_observation');
    }
}
