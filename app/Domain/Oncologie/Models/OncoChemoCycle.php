<?php

namespace App\Domain\Oncologie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OncoChemoCycle extends Model
{
    use LogsActivity;

    protected $fillable = [
        'onco_record_id',
        'cycle_number',
        'cycle_date',
        'medications',
        'side_effects',
    ];

    protected function casts(): array
    {
        return [
            'cycle_date' => 'date',
        ];
    }

    public function oncoRecord(): BelongsTo
    {
        return $this->belongsTo(OncoRecord::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('onco_chemo_cycle');
    }
}
