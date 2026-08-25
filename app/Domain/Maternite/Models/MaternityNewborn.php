<?php

namespace App\Domain\Maternite\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MaternityNewborn extends Model
{
    use LogsActivity;

    protected $fillable = [
        'maternity_delivery_id',
        'sex',
        'birth_weight_grams',
        'apgar_1min',
        'apgar_5min',
        'apgar_10min',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(MaternityDelivery::class, 'maternity_delivery_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('maternity_newborn');
    }
}
