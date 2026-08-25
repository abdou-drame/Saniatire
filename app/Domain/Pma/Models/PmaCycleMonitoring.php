<?php

namespace App\Domain\Pma\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PmaCycleMonitoring extends Model
{
    use LogsActivity;

    protected $fillable = [
        'pma_record_id',
        'monitoring_date',
        'echo_observations',
        'hormone_level',
        'puncture_date',
        'transfer_date',
    ];

    protected function casts(): array
    {
        return [
            'monitoring_date' => 'date',
            'hormone_level' => 'decimal:2',
            'puncture_date' => 'date',
            'transfer_date' => 'date',
            // Étape 9 §2 : voir MentalHealthRecord pour la justification.
            'echo_observations' => 'encrypted',
        ];
    }

    public function pmaRecord(): BelongsTo
    {
        return $this->belongsTo(PmaRecord::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->logExcept(['echo_observations'])
            ->useLogName('pma_cycle_monitoring');
    }
}
