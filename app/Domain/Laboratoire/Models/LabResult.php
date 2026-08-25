<?php

namespace App\Domain\Laboratoire\Models;

use App\Domain\User\Models\User;
use Database\Factories\LabResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Tenant isolation is inherited through lab_sample_id -> lab_order_id — no
 * BelongsToTenant of its own. Two distinct validators (technical, then
 * biological) are tracked with their own user/timestamp pair; the
 * controller enforces that biological validation cannot happen before
 * technical validation.
 */
class LabResult extends Model
{
    /** @use HasFactory<LabResultFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'lab_sample_id',
        'lab_order_item_id',
        'value',
        'unit',
        'reference_min',
        'reference_max',
        'interpretation',
        'status',
        'technical_validated_by',
        'technical_validated_at',
        'biological_validated_by',
        'biological_validated_at',
    ];

    protected function casts(): array
    {
        return [
            'reference_min' => 'decimal:3',
            'reference_max' => 'decimal:3',
            'technical_validated_at' => 'datetime',
            'biological_validated_at' => 'datetime',
        ];
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(LabSample::class, 'lab_sample_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(LabOrderItem::class, 'lab_order_item_id');
    }

    public function technicalValidator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technical_validated_by');
    }

    public function biologicalValidator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'biological_validated_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('lab_result');
    }
}
