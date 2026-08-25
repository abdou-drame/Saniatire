<?php

namespace App\Domain\Rh\Models;

use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\Structure\Models\Site;
use App\Domain\User\Models\User;
use Database\Factories\WorkScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WorkSchedule extends Model
{
    /** @use HasFactory<WorkScheduleFactory> */
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'structure_id',
        'user_id',
        'site_id',
        'jour_semaine',
        'date',
        'heure_debut',
        'heure_fin',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'jour_semaine' => 'integer',
            'date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * True if this schedule template applies on the given calendar day —
     * either it's the one-off "date" entry for that day, or it's a
     * recurring weekly template ("date" null) whose jour_semaine matches.
     */
    public function appliesOn(\Carbon\CarbonInterface $day): bool
    {
        return $this->date
            ? $this->date->isSameDay($day)
            : $this->jour_semaine === $day->dayOfWeek;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('work_schedule');
    }
}
