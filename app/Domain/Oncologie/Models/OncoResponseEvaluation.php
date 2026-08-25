<?php

namespace App\Domain\Oncologie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OncoResponseEvaluation extends Model
{
    use LogsActivity;

    protected $fillable = [
        'onco_record_id',
        'evaluated_at',
        'response',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'evaluated_at' => 'date',
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
            ->useLogName('onco_response_evaluation');
    }
}
