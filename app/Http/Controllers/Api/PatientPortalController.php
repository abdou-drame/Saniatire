<?php

namespace App\Http\Controllers\Api;

use App\Domain\Appointment\Events\RendezVousCree;
use App\Domain\Appointment\Models\Appointment;
use App\Domain\Facturation\Models\Invoice;
use App\Domain\Imagerie\Models\ImagingReport;
use App\Domain\Laboratoire\Models\LabResult;
use App\Domain\Shared\Scheduling\PractitionerPresenceService;
use App\Domain\Structure\Models\Site;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\InvoiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Étape 7b §2 : chaque endpoint dérive l'identité du patient UNIQUEMENT de
 * $request->user() (guard `patient`) — jamais d'un paramètre d'URL/payload —
 * ce qui garantit qu'un patient ne peut jamais lire/écrire les données d'un
 * autre patient, même en devinant/modifiant un id.
 */
class PatientPortalController extends Controller
{
    public function appointments(Request $request): JsonResponse
    {
        $appointments = Appointment::query()
            ->where('patient_id', $request->user()->id)
            ->orderByDesc('starts_at')
            ->paginate();

        return AppointmentResource::collection($appointments)->response();
    }

    /**
     * Créneaux libres d'un praticien de sa structure sur [from, to] :
     * réutilise PractitionerPresenceService::planningFor() (présence
     * théorique RH) puis retire les créneaux déjà occupés via
     * Appointment::hasConflict(), même primitives que
     * AppointmentController::store().
     */
    public function creneauxDisponibles(Request $request): JsonResponse
    {
        $data = $request->validate([
            'practitioner_id' => ['required', 'integer', 'exists:users,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'duration_minutes' => ['sometimes', 'integer', 'min:5', 'max:480'],
        ]);

        $structureId = $request->user()->structure_id;
        // Laravel's 'integer' validation rule checks the shape but doesn't
        // cast — a GET query-string value stays a string, which Carbon's
        // addMinutes() (strict int|float since a recent version) rejects.
        $duration = (int) ($data['duration_minutes'] ?? 30);
        $from = Carbon::parse($data['from']);
        $to = Carbon::parse($data['to']);

        $planning = app(PractitionerPresenceService::class)->planningFor($structureId, $data['practitioner_id'], $from, $to);

        $creneaux = [];

        foreach ($planning['horaires'] as $horaire) {
            $day = $horaire['date'];
            $slotStart = Carbon::parse("{$day} {$horaire['heure_debut']}");
            $slotEnd = Carbon::parse("{$day} {$horaire['heure_fin']}");

            for ($cursor = $slotStart->clone(); $cursor->clone()->addMinutes($duration)->lte($slotEnd); $cursor->addMinutes($duration)) {
                if (! Appointment::hasConflict($structureId, $data['practitioner_id'], $cursor, $duration)) {
                    $creneaux[] = $cursor->toIso8601String();
                }
            }
        }

        return response()->json(['creneaux' => $creneaux]);
    }

    public function storeAppointment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'practitioner_id' => ['required', 'integer', 'exists:users,id'],
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $patient = $request->user();
        $startsAt = Carbon::parse($data['starts_at']);

        if (Appointment::hasConflict($patient->structure_id, $data['practitioner_id'], $startsAt, $data['duration_minutes'])) {
            return response()->json(['message' => 'Le praticien est déjà occupé sur ce créneau.'], 422);
        }

        // Contrairement au flux staff (AppointmentController::presenceCheckResponse),
        // un patient ne dispose d'aucune dérogation possible : hors planning
        // théorique du praticien (horaires RH, congés validés), la demande
        // est toujours refusée, jamais forçable depuis le portail patient.
        $endsAt = $startsAt->clone()->addMinutes($data['duration_minutes']);
        $presence = app(PractitionerPresenceService::class)->isPresent($patient->structure_id, $data['practitioner_id'], $startsAt, $endsAt);

        if (! $presence['present']) {
            return response()->json(['message' => "Praticien indisponible sur ce créneau : {$presence['reason']}"], 422);
        }

        $appointment = Appointment::create([
            'site_id' => $data['site_id'],
            'patient_id' => $patient->id,
            'practitioner_id' => $data['practitioner_id'],
            'starts_at' => $startsAt,
            'duration_minutes' => $data['duration_minutes'],
            'reason' => $data['reason'] ?? null,
            'status' => 'planifie',
        ])->refresh();

        RendezVousCree::dispatch($appointment);

        return (new AppointmentResource($appointment))->response()->setStatusCode(201);
    }

    public function invoices(Request $request): JsonResponse
    {
        $invoices = Invoice::query()
            ->where('patient_id', $request->user()->id)
            ->with('payments')
            ->orderByDesc('date_emission')
            ->paginate();

        return InvoiceResource::collection($invoices)->response();
    }

    public function solde(Request $request): JsonResponse
    {
        $solde = Invoice::query()
            ->where('patient_id', $request->user()->id)
            ->whereIn('statut', ['emise', 'partiellement_payee'])
            ->with('payments')
            ->get()
            ->sum(fn (Invoice $invoice) => $invoice->solde());

        return response()->json(['solde' => round($solde, 2)]);
    }

    /**
     * Détail d'une facture : lignes de prestation + historique des
     * paiements. `Invoice` étant déjà `BelongsToTenant`, une facture d'une
     * autre structure est déjà invisible (404 via le model binding) ; la
     * vérification `patient_id` couvre le cas restant d'une facture d'un
     * autre patient de la même structure — toujours un 404, jamais un 403,
     * pour ne pas confirmer l'existence de la ressource.
     */
    public function invoice(Request $request, Invoice $invoice): InvoiceResource
    {
        abort_unless($invoice->patient_id === $request->user()->id, 404);

        return new InvoiceResource($invoice->load(['items', 'payments']));
    }

    /**
     * Sites de la structure du patient, pour le choix du lieu de RDV.
     * `/sites` (staff) exige une permission Spatie que le modèle Patient
     * n'a pas — cette route ne fait que rendre visible, sous le guard
     * `patient`, ce que TenantScope borne déjà à la structure du patient.
     */
    public function sites(Request $request): JsonResponse
    {
        $sites = Site::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => $sites]);
    }

    /**
     * Mêmes praticiens (role `medecin`, actifs) que PractitionerController
     * ::index côté staff, pour ne pas introduire une règle de sélection
     * différente entre les deux flux de prise de RDV.
     */
    public function practitioners(): JsonResponse
    {
        $practitioners = User::query()
            ->role('medecin')
            ->where('is_active', true)
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);

        return response()->json([
            'data' => $practitioners->map(fn (User $user) => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
            ]),
        ]);
    }

    /**
     * Résultats transmis uniquement (labo + imagerie) — un résultat non
     * encore transmis au patient ne doit jamais apparaître ici, même s'il
     * est déjà validé biologiquement/médicalement en interne.
     *
     * Réponse volontairement en DTO propre au portail plutôt qu'un
     * ResultResource/ImagingReportResource générique : ces Resources
     * partagées n'exposent que des ids bruts (validateur, auteur), sans
     * libellé LOINC ni nom de praticien — illisible pour un patient.
     */
    public function documents(Request $request): JsonResponse
    {
        $patientId = $request->user()->id;

        $labResults = LabResult::query()
            ->where('status', 'transmis')
            ->whereHas('orderItem.labOrder', fn ($q) => $q->where('patient_id', $patientId))
            ->with(['orderItem.loincCode', 'biologicalValidator', 'technicalValidator'])
            ->get();

        $imagingReports = ImagingReport::query()
            ->where('status', 'valide')
            ->whereHas('study', function ($q) use ($patientId) {
                $q->where('status', 'transmis')
                    ->whereHas('imagingOrder', fn ($q2) => $q2->where('patient_id', $patientId));
            })
            ->with(['study.imagingOrder', 'author'])
            ->get();

        return response()->json([
            'resultats_laboratoire' => $labResults->map(fn (LabResult $result) => [
                'id' => $result->id,
                'type' => $result->orderItem?->loincCode?->label,
                'date' => ($result->biological_validated_at ?? $result->technical_validated_at ?? $result->created_at)?->toIso8601String(),
                'praticien' => self::practitionerLabel($result->biologicalValidator ?? $result->technicalValidator),
                'value' => $result->value,
                'unit' => $result->unit,
                'reference_min' => $result->reference_min,
                'reference_max' => $result->reference_max,
                'interpretation' => $result->interpretation,
            ]),
            'comptes_rendus_imagerie' => $imagingReports->map(fn (ImagingReport $report) => [
                'id' => $report->id,
                'type' => $report->study?->imagingOrder?->exam_type,
                'date' => ($report->validated_at ?? $report->created_at)?->toIso8601String(),
                'praticien' => self::practitionerLabel($report->author),
                'content' => $report->content,
            ]),
        ]);
    }

    private static function practitionerLabel(?User $user): ?string
    {
        return $user ? "Dr {$user->first_name} {$user->last_name}" : null;
    }
}
