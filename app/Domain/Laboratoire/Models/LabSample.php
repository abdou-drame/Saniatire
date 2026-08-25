<?php

namespace App\Domain\Laboratoire\Models;

use App\Domain\User\Models\User;
use Database\Factories\LabSampleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Tenant isolation is inherited through lab_order_id — no BelongsToTenant
 * of its own, same pattern as LabOrderItem.
 */
class LabSample extends Model
{
    /** @use HasFactory<LabSampleFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'lab_order_id',
        'barcode',
        'sample_type',
        'collected_at',
        'collected_by',
    ];

    protected function casts(): array
    {
        return [
            'collected_at' => 'datetime',
        ];
    }

    public function labOrder(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class);
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(LabResult::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('lab_sample');
    }
}
