<?php

namespace App\Domain\Laboratoire\Models;

use Database\Factories\LabOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One requested analysis line on a lab_order. Tenant isolation is
 * inherited through lab_order_id, same pattern as ConsultationDiagnosis
 * under Consultation — no BelongsToTenant of its own.
 */
class LabOrderItem extends Model
{
    /** @use HasFactory<LabOrderItemFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'lab_order_id',
        'loinc_code_id',
        'status',
    ];

    public function labOrder(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class);
    }

    public function loincCode(): BelongsTo
    {
        return $this->belongsTo(LoincCode::class);
    }

    public function result(): HasOne
    {
        return $this->hasOne(LabResult::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('lab_order_item');
    }
}
