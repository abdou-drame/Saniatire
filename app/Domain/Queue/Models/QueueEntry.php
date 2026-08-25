<?php

namespace App\Domain\Queue\Models;

use App\Domain\Appointment\Models\Appointment;
use App\Domain\Patient\Models\Patient;
use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\Structure\Models\Site;
use App\Domain\User\Models\User;
use Database\Factories\QueueEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class QueueEntry extends Model
{
    /** @use HasFactory<QueueEntryFactory> */
    use BelongsToTenant, HasFactory, LogsActivity;

    /**
     * The status timestamp column touched when transitioning *into* the
     * given status, used both to stamp the transition and to compute wait
     * times after the fact.
     */
    public const STATUS_TIMESTAMP_COLUMNS = [
        'appele' => 'called_at',
        'en_consultation' => 'in_consultation_at',
        'sorti' => 'exited_at',
    ];

    protected $fillable = [
        'structure_id',
        'site_id',
        'patient_id',
        'appointment_id',
        'practitioner_id',
        'service',
        'priority',
        'status',
        'arrived_at',
        'called_at',
        'in_consultation_at',
        'exited_at',
    ];

    protected function casts(): array
    {
        return [
            'arrived_at' => 'datetime',
            'called_at' => 'datetime',
            'in_consultation_at' => 'datetime',
            'exited_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'practitioner_id');
    }

    /**
     * Minutes between arrival and being called, once known — null while
     * still waiting.
     */
    public function waitMinutes(): ?int
    {
        if (! $this->called_at) {
            return null;
        }

        return $this->arrived_at->diffInMinutes($this->called_at);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('queue_entry');
    }
}
