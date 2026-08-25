<?php

namespace App\Http\Controllers\Api;

use App\Domain\Appointment\Events\RendezVousAnnule;
use App\Domain\Appointment\Events\RendezVousCree;
use App\Domain\Appointment\Events\RendezVousModifie;
use App\Domain\Appointment\Models\Appointment;
use App\Domain\Shared\Scheduling\PractitionerPresenceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentRequest;
use App\Http\Resources\AppointmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;

class AppointmentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:appointments.view', only: ['index', 'show']),
            new Middleware('permission:appointments.create', only: ['store']),
            new Middleware('permission:appointments.update', only: ['update']),
            new Middleware('permission:appointments.cancel', only: ['cancel']),
        ];
    }

    /**
     * Calendar view: filterable by date range (?from=&to=, defaults to the
     * current day so "day view" works with no params), practitioner and
     * site — covers the day/week/month views asked for, since a week or
     * month view is just a wider [from, to].
     */
    public function index(Request $request): JsonResponse
    {
        // Callers pass plain dates ("2026-08-25") for day/week/month views;
        // Carbon parses a bare date at midnight, so without normalizing to
        // day boundaries a single-day query (from == to) would only match
        // appointments starting exactly at 00:00:00.
        $from = ($request->date('from') ?? now())->startOfDay();
        $to = ($request->date('to') ?? now())->endOfDay();

        $appointments = Appointment::query()
            ->whereBetween('starts_at', [$from, $to])
            ->when($request->integer('practitioner_id'), fn ($q, $id) => $q->where('practitioner_id', $id))
            ->when($request->integer('site_id'), fn ($q, $id) => $q->where('site_id', $id))
            ->orderBy('starts_at')
            ->paginate();

        return AppointmentResource::collection($appointments)->response();
    }

    public function store(AppointmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $structureId = $request->user()->structure_id;
        $startsAt = Carbon::parse($data['starts_at']);

        if (Appointment::hasConflict($structureId, $data['practitioner_id'], $startsAt, $data['duration_minutes'], $data['resource_name'] ?? null)) {
            return response()->json([
                'message' => 'Le praticien (ou la ressource) est déjà occupé sur ce créneau.',
            ], 422);
        }

        if ($response = $this->presenceCheckResponse($request, $structureId, $data['practitioner_id'], $startsAt, $data['duration_minutes'])) {
            return $response;
        }

        $appointment = Appointment::create($data)->refresh();

        RendezVousCree::dispatch($appointment);

        return (new AppointmentResource($appointment))->response()->setStatusCode(201);
    }

    public function show(Appointment $appointment): AppointmentResource
    {
        return new AppointmentResource($appointment);
    }

    public function update(AppointmentRequest $request, Appointment $appointment): JsonResponse|AppointmentResource
    {
        $data = $request->validated();

        $practitionerId = $data['practitioner_id'] ?? $appointment->practitioner_id;
        $startsAt = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : $appointment->starts_at;
        $durationMinutes = $data['duration_minutes'] ?? $appointment->duration_minutes;
        $resourceName = $data['resource_name'] ?? $appointment->resource_name;

        if (Appointment::hasConflict($appointment->structure_id, $practitionerId, $startsAt, $durationMinutes, $resourceName, excludingAppointmentId: $appointment->id)) {
            return response()->json([
                'message' => 'Le praticien (ou la ressource) est déjà occupé sur ce créneau.',
            ], 422);
        }

        if ($response = $this->presenceCheckResponse($request, $appointment->structure_id, $practitionerId, $startsAt, $durationMinutes)) {
            return $response;
        }

        $appointment->update($data);

        RendezVousModifie::dispatch($appointment);

        return new AppointmentResource($appointment);
    }

    /**
     * Cancellation is a status transition, not a delete: the appointment
     * stays in the calendar/audit trail as "annulé" rather than
     * disappearing, and freeing the slot is handled by hasConflict()
     * excluding cancelled appointments.
     */
    public function cancel(Appointment $appointment): AppointmentResource
    {
        $appointment->update(['status' => 'annule']);

        RendezVousAnnule::dispatch($appointment);

        return new AppointmentResource($appointment);
    }

    /**
     * Étape 6 §3 : extension de la vérification de disponibilité existante
     * — appelée juste à côté de Appointment::hasConflict(), avec le même
     * contrat d'erreur (422 + "message"), plutôt que fusionnée dedans.
     * hasConflict() reste inchangé (responsabilité unique : chevauchement
     * rendez-vous/rendez-vous, domaine Appointment) pour ne prendre aucun
     * risque de régression sur les tests de l'étape 2 ; la disponibilité
     * théorique du praticien (horaires RH + gardes/astreintes − congés
     * validés) est un second contrôle composé ici, au même endroit exact
     * où hasConflict() est déjà invoqué.
     *
     * Une dérogation (force_override=true) n'est acceptée que si
     * l'utilisateur a la permission appointments.override_planning ; dans
     * ce cas le contrôle est court-circuité et l'action est tracée dans
     * un journal d'audit dédié (log_name "derogation_planning"), sur le
     * même modèle que PmaRecordController::logSensitiveAccess().
     */
    private function presenceCheckResponse(AppointmentRequest $request, int $structureId, int $practitionerId, Carbon $startsAt, int $durationMinutes): ?JsonResponse
    {
        $endsAt = $startsAt->clone()->addMinutes($durationMinutes);
        $presence = app(PractitionerPresenceService::class)->isPresent($structureId, $practitionerId, $startsAt, $endsAt);

        if ($presence['present']) {
            return null;
        }

        $forceOverride = $request->boolean('force_override');

        if ($forceOverride && $request->user()->can('appointments.override_planning')) {
            activity('derogation_planning')
                ->causedBy($request->user())
                ->withProperties([
                    'practitioner_id' => $practitionerId,
                    'starts_at' => $startsAt->toDateTimeString(),
                    'duration_minutes' => $durationMinutes,
                    'raison' => $presence['reason'],
                ])
                ->log('Rendez-vous forcé hors planning théorique du praticien');

            return null;
        }

        return response()->json([
            'message' => $forceOverride
                ? "Vous n'avez pas la permission de déroger au planning."
                : "Praticien indisponible sur ce créneau : {$presence['reason']}",
        ], 422);
    }
}
