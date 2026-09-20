<?php

namespace App\Domain\Shared\Scheduling;

use App\Domain\Rh\Models\LeaveRequest;
use App\Domain\Rh\Models\WorkSchedule;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PractitionerPresenceService
{
    /**
     * Types de work_schedule qui ne comptent jamais comme un créneau de
     * consultation réservable : garde/astreinte sont de la couverture
     * d'urgence/sur-appel, pas des plages ouvertes à la prise de RDV.
     * Règle globale — s'applique à isPresent() (donc à toute création/
     * modification de RDV, staff comme patient) et, sur demande explicite
     * du caller, à planningFor() (voir PatientPortalController). Le staff
     * garde une échappatoire : force_override + permission
     * appointments.override_planning (AppointmentController::presenceCheckResponse).
     */
    public const ON_CALL_TYPES = ['garde', 'astreinte'];

    /**
     * Theoretical presence of a practitioner on [startsAt, endsAt): absent
     * if a validated leave covers the day, otherwise present only if a
     * 'normal' work_schedule covers the whole slot — self::ON_CALL_TYPES
     * never count here, on-call hours are not bookable consultation time.
     *
     * A practitioner with zero work_schedules configured at all is treated
     * as unrestricted rather than permanently absent — RH planning is
     * opt-in per user, so practitioners nobody has scheduled yet (or who
     * predate this module) keep booking exactly as before étape 6.
     */
    public function isPresent(int $structureId, int $userId, CarbonInterface $startsAt, CarbonInterface $endsAt): array
    {
        $leave = $this->leaveCovering($structureId, $userId, $startsAt);

        if ($leave) {
            return [
                'present' => false,
                'reason' => "Le praticien est en congé validé ({$leave->type}) du {$leave->date_debut->toDateString()} au {$leave->date_fin->toDateString()}.",
            ];
        }

        $hasAnySchedule = WorkSchedule::query()
            ->where('structure_id', $structureId)
            ->where('user_id', $userId)
            ->exists();

        if (! $hasAnySchedule) {
            return ['present' => true, 'reason' => null];
        }

        $covering = $this->schedulesCovering($structureId, $userId, $startsAt, $endsAt);

        if ($covering->isEmpty()) {
            return [
                'present' => false,
                'reason' => 'Aucun horaire normal planifié ne couvre ce créneau (garde/astreinte non réservables).',
            ];
        }

        return ['present' => true, 'reason' => null];
    }

    /**
     * 'normal' work_schedule rows only (self::ON_CALL_TYPES excluded, see
     * isPresent()) whose window fully contains [startsAt, endsAt) on
     * startsAt's calendar day — narrowed in SQL by day (date match or
     * recurring jour_semaine match), finished in PHP for the time-window
     * comparison, mirroring the "SQL narrows, PHP finishes" portability
     * doctrine used by Appointment::hasConflict().
     */
    private function schedulesCovering(int $structureId, int $userId, CarbonInterface $startsAt, CarbonInterface $endsAt): Collection
    {
        return $this->candidateSchedules($structureId, $userId, $startsAt)
            ->filter(function (WorkSchedule $schedule) use ($startsAt, $endsAt) {
                $day = $startsAt->toDateString();
                $windowStart = Carbon::parse("{$day} {$schedule->heure_debut}");
                $windowEnd = Carbon::parse("{$day} {$schedule->heure_fin}");

                return $startsAt->gte($windowStart) && $endsAt->lte($windowEnd);
            });
    }

    private function candidateSchedules(int $structureId, int $userId, CarbonInterface $day): Collection
    {
        return WorkSchedule::query()
            ->where('structure_id', $structureId)
            ->where('user_id', $userId)
            ->whereNotIn('type', self::ON_CALL_TYPES)
            ->where(function ($q) use ($day) {
                $q->whereDate('date', $day->toDateString())
                    ->orWhere(function ($q2) use ($day) {
                        $q2->whereNull('date')->where('jour_semaine', $day->dayOfWeek);
                    });
            })
            ->get();
    }

    /**
     * date_debut/date_fin are "date"-cast attributes, which Eloquent
     * serializes to the DB as "Y-m-d H:i:s" (not a bare date) — comparing
     * them with plain `where(...)` against a "Y-m-d" string silently fails
     * (string "2026-09-03 00:00:00" sorts after "2026-09-03"), so every
     * comparison here goes through whereDate(), which extracts just the
     * date part in SQL regardless of how the column was serialized.
     */
    private function leaveCovering(int $structureId, int $userId, CarbonInterface $day): ?LeaveRequest
    {
        return LeaveRequest::query()
            ->where('structure_id', $structureId)
            ->where('user_id', $userId)
            ->where('statut', 'valide')
            ->whereDate('date_debut', '<=', $day->toDateString())
            ->whereDate('date_fin', '>=', $day->toDateString())
            ->first();
    }

    /**
     * Combined planning over a period: work_schedule entries expanded day
     * by day across [from, to], with days covered by a validated leave
     * excluded from the horaires list and reported separately.
     *
     * $excludedTypes lets a caller drop garde/astreinte rows entirely (see
     * PatientPortalController::creneauxDisponibles, which never offers
     * on-call hours as bookable slots to a patient) while every other
     * caller — the staff Plannings screen in particular — keeps seeing the
     * full picture by passing none.
     */
    public function planningFor(int $structureId, int $userId, CarbonInterface $from, CarbonInterface $to, array $excludedTypes = []): array
    {
        $schedules = WorkSchedule::query()
            ->where('structure_id', $structureId)
            ->where('user_id', $userId)
            ->when($excludedTypes !== [], fn ($q) => $q->whereNotIn('type', $excludedTypes))
            ->where(function ($q) use ($from, $to) {
                $q->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())
                    ->orWhereNull('date');
            })
            ->get();

        $leaves = LeaveRequest::query()
            ->where('structure_id', $structureId)
            ->where('user_id', $userId)
            ->where('statut', 'valide')
            ->whereDate('date_debut', '<=', $to->toDateString())
            ->whereDate('date_fin', '>=', $from->toDateString())
            ->get();

        $joursConges = [];
        $horaires = [];

        foreach (CarbonPeriod::create($from->clone()->startOfDay(), $to->clone()->startOfDay()) as $day) {
            $onLeave = $leaves->first(fn (LeaveRequest $leave) => $leave->coversDate($day));

            if ($onLeave) {
                $joursConges[] = $day->toDateString();

                continue;
            }

            foreach ($schedules as $schedule) {
                if (! $schedule->appliesOn($day)) {
                    continue;
                }

                $horaires[] = [
                    'date' => $day->toDateString(),
                    'work_schedule_id' => $schedule->id,
                    'site_id' => $schedule->site_id,
                    'type' => $schedule->type,
                    'heure_debut' => $schedule->heure_debut,
                    'heure_fin' => $schedule->heure_fin,
                ];
            }
        }

        return [
            'horaires' => $horaires,
            'jours_conges' => $joursConges,
            'conges' => $leaves,
        ];
    }

    /**
     * Who is on garde/astreinte over a period (§4: vue dédiée, filtrable
     * site/service), one entry per (day, schedule) pair, excluding users
     * on validated leave that day.
     */
    public function onCallBetween(int $structureId, ?int $siteId, CarbonInterface $from, CarbonInterface $to): array
    {
        $schedules = WorkSchedule::query()
            ->where('structure_id', $structureId)
            ->whereIn('type', self::ON_CALL_TYPES)
            ->when($siteId, fn ($q, $id) => $q->where('site_id', $id))
            ->where(function ($q) use ($from, $to) {
                $q->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())
                    ->orWhereNull('date');
            })
            ->get();

        $entries = [];

        foreach (CarbonPeriod::create($from->clone()->startOfDay(), $to->clone()->startOfDay()) as $day) {
            foreach ($schedules as $schedule) {
                if (! $schedule->appliesOn($day) || $this->leaveCovering($structureId, $schedule->user_id, $day)) {
                    continue;
                }

                $entries[] = [
                    'date' => $day->toDateString(),
                    'user_id' => $schedule->user_id,
                    'site_id' => $schedule->site_id,
                    'type' => $schedule->type,
                    'heure_debut' => $schedule->heure_debut,
                    'heure_fin' => $schedule->heure_fin,
                ];
            }
        }

        return $entries;
    }

    /**
     * Who is on garde/astreinte right now — emergency lookup, no period.
     */
    public function onCallNow(int $structureId, ?int $siteId, CarbonInterface $at): Collection
    {
        $time = $at->format('H:i:s');

        return $this->candidateGardeAstreinteSchedules($structureId, $siteId, $at)
            ->filter(fn (WorkSchedule $schedule) => ! $this->leaveCovering($structureId, $schedule->user_id, $at))
            ->filter(fn (WorkSchedule $schedule) => $schedule->heure_debut <= $time && $schedule->heure_fin >= $time)
            ->values();
    }

    private function candidateGardeAstreinteSchedules(int $structureId, ?int $siteId, CarbonInterface $day): Collection
    {
        return WorkSchedule::query()
            ->where('structure_id', $structureId)
            ->whereIn('type', self::ON_CALL_TYPES)
            ->when($siteId, fn ($q, $id) => $q->where('site_id', $id))
            ->where(function ($q) use ($day) {
                $q->whereDate('date', $day->toDateString())
                    ->orWhere(function ($q2) use ($day) {
                        $q2->whereNull('date')->where('jour_semaine', $day->dayOfWeek);
                    });
            })
            ->get();
    }

    /**
     * work_schedule entries a just-validated leave overlaps — surfaced as
     * a non-blocking warning (see LeaveRequestController::validateRequest):
     * blocking the validation would prevent approving a legitimate leave
     * just because a stale schedule entry hasn't been cleaned up yet.
     */
    public function scheduleOverlapWarnings(int $structureId, LeaveRequest $leave): array
    {
        $schedules = WorkSchedule::query()
            ->where('structure_id', $structureId)
            ->where('user_id', $leave->user_id)
            ->get();

        $warnings = [];

        foreach (CarbonPeriod::create($leave->date_debut, $leave->date_fin) as $day) {
            foreach ($schedules as $schedule) {
                if ($schedule->appliesOn($day)) {
                    $warnings[] = [
                        'type' => 'overlap_schedule',
                        'work_schedule_id' => $schedule->id,
                        'date' => $day->toDateString(),
                        'schedule_type' => $schedule->type,
                    ];
                }
            }
        }

        return $warnings;
    }
}
