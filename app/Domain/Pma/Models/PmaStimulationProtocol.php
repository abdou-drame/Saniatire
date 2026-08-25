<?php

namespace App\Domain\Pma\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PmaStimulationProtocol extends Model
{
    use LogsActivity;

    protected $fillable = [
        'pma_record_id',
        'protocol_type',
        'medications',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
            // Étape 9 §2 : voir MentalHealthRecord pour la justification.
            'medications' => 'encrypted',
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
            ->logExcept(['medications'])
            ->useLogName('pma_stimulation_protocol');
    }
}
