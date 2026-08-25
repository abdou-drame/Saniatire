<?php

namespace App\Domain\Maternite\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MaternityPostpartumVisit extends Model
{
    use LogsActivity;

    protected $fillable = [
        'maternity_record_id',
        'practitioner_id',
        'visit_date',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'temperature_c',
        'bleeding_status',
        'breastfeeding_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'temperature_c' => 'decimal:1',
        ];
    }

    public function maternityRecord(): BelongsTo
    {
        return $this->belongsTo(MaternityRecord::class);
    }

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'practitioner_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('maternity_postpartum_visit');
    }
}
