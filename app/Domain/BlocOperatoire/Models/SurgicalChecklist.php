<?php

namespace App\Domain\BlocOperatoire\Models;

use App\Domain\User\Models\User;
use Database\Factories\SurgicalChecklistFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One of the 3 mandatory checklist steps for a surgical_procedure. Tenant
 * isolation is inherited through surgical_procedure_id, no BelongsToTenant
 * of its own — same pattern as ConsultationDiagnosis under Consultation.
 */
class SurgicalChecklist extends Model
{
    /** @use HasFactory<SurgicalChecklistFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'surgical_procedure_id',
        'step',
        'items',
        'validated_by',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'validated_at' => 'datetime',
        ];
    }

    public function surgicalProcedure(): BelongsTo
    {
        return $this->belongsTo(SurgicalProcedure::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('surgical_checklist');
    }
}
