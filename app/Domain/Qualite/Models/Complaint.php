<?php

namespace App\Domain\Qualite\Models;

use App\Domain\Patient\Models\Patient;
use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\User\Models\User;
use Database\Factories\ComplaintFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Complaint extends Model
{
    /** @use HasFactory<ComplaintFactory> */
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'structure_id',
        'patient_id',
        'gestionnaire_id',
        'motif',
        'description',
        'service_concerne',
        'origin',
        'statut',
        'resolved_at',
        'resolved_by',
        'closed_at',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function gestionnaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gestionnaire_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ComplaintResponse::class)->orderBy('created_at');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('complaint');
    }
}
