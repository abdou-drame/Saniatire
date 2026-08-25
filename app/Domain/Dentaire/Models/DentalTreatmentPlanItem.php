<?php

namespace App\Domain\Dentaire\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DentalTreatmentPlanItem extends Model
{
    use LogsActivity;

    protected $fillable = [
        'dental_treatment_plan_id',
        'tooth_fdi',
        'act_type',
        'status',
        'planned_at',
    ];

    protected function casts(): array
    {
        return [
            'planned_at' => 'date',
        ];
    }

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(DentalTreatmentPlan::class, 'dental_treatment_plan_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('dental_treatment_plan_item');
    }
}
