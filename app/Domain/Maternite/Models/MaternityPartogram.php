<?php

namespace App\Domain\Maternite\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MaternityPartogram extends Model
{
    use LogsActivity;

    protected $fillable = [
        'maternity_record_id',
        'labor_started_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'labor_started_at' => 'datetime',
        ];
    }

    public function maternityRecord(): BelongsTo
    {
        return $this->belongsTo(MaternityRecord::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(MaternityPartogramReading::class)->orderBy('recorded_at');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('maternity_partogram');
    }
}
