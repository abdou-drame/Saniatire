<?php

namespace App\Domain\Appointment\Models;

use App\Domain\Patient\Models\Patient;
use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\Structure\Models\Site;
use App\Domain\User\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\AppointmentFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    /**
     * A cancelled or no-show appointment no longer occupies the slot, so it
     * must not block a new booking for the same practitioner/resource.
     */
    private const NON_BLOCKING_STATUSES = ['annule', 'absent'];

    protected $fillable = [
        'structure_id',
        'site_id',
        'patient_id',
        'practitioner_id',
        'appointment_series_id',
        'resource_name',
        'starts_at',
        'duration_minutes',
        'reason',
        'status',
        'is_recurring',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'is_recurring' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'practitioner_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(AppointmentSeries::class, 'appointment_series_id');
    }

    public function endsAt(): CarbonInterface
    {
        return $this->starts_at->clone()->addMinutes($this->duration_minutes);
    }

    /**
     * True if this practitioner (and, when given, this resource) already
     * has a non-cancelled appointment overlapping [startsAt, startsAt +
     * durationMinutes) within the caller's own structure.
     *
     * The "row start + row duration > newStart" half of the overlap check
     * is evaluated in PHP rather than SQL (starts_at < newEnd narrows the
     * candidate set first) because it must stay portable across Postgres
     * (production) and SQLite (test suite), which have no common interval
     * arithmetic syntax.
     */
    public static function hasConflict(
        int $structureId,
        int $practitionerId,
        DateTimeInterface $startsAt,
        int $durationMinutes,
        ?string $resourceName = null,
        ?int $excludingAppointmentId = null,
    ): bool {
        $startsAt = Carbon::instance($startsAt);
        $endsAt = $startsAt->clone()->addMinutes($durationMinutes);

        $query = static::query()
            ->where('structure_id', $structureId)
            ->whereNotIn('status', self::NON_BLOCKING_STATUSES)
            ->where('starts_at', '<', $endsAt)
            ->where(function ($q) use ($practitionerId, $resourceName) {
                $q->where('practitioner_id', $practitionerId);

                if ($resourceName) {
                    $q->orWhere('resource_name', $resourceName);
                }
            });

        if ($excludingAppointmentId) {
            $query->where('id', '!=', $excludingAppointmentId);
        }

        return $query->get(['starts_at', 'duration_minutes'])
            ->contains(fn (self $appointment) => $appointment->endsAt()->gt($startsAt));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('appointment');
    }
}
