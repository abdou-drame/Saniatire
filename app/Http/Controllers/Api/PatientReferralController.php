<?php

namespace App\Http\Controllers\Api;

use App\Domain\Patient\Models\Patient;
use App\Domain\Referral\Events\ReferencementAccepte;
use App\Domain\Referral\Events\ReferencementRefuse;
use App\Domain\Referral\Models\PatientReferral;
use App\Domain\Shared\Tenancy\TenantScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\PatientReferralResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Étape 7b §5 : seul endroit du projet où un partage de données entre deux
 * structure_id est intentionnel. La visibilité est bornée par
 * ReferralVisibilityScope (jamais un withoutGlobalScopes() général), et
 * chaque franchissement de frontière est tracé explicitement via
 * activity('partage_inter_structure') — même patron que 'acces_sensible'
 * dans bootstrap/app.php.
 */
class PatientReferralController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:referrals.view', only: ['index', 'show']),
            new Middleware('permission:referrals.create', only: ['store']),
            new Middleware('permission:referrals.accept', only: ['accept']),
            new Middleware('permission:referrals.refuse', only: ['refuse']),
            new Middleware('permission:referrals.update', only: ['completeWithReport']),
        ];
    }

    public function index(): JsonResponse
    {
        $referrals = PatientReferral::query()->orderByDesc('created_at')->paginate();

        return PatientReferralResource::collection($referrals)->response();
    }

    public function show(Request $request, PatientReferral $referral): PatientReferralResource
    {
        $this->auditSiNonOrigine($request, $referral, 'consultation');

        return new PatientReferralResource($referral);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'structure_destination_id' => ['required', 'integer', 'exists:structures,id'],
            'site_origine_id' => ['nullable', 'integer', 'exists:sites,id'],
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'praticien_referent_id' => ['required', 'integer', 'exists:users,id'],
            'motif' => ['required', 'string'],
        ]);

        $referral = PatientReferral::create([
            'structure_origine_id' => $request->user()->structure_id,
            'site_origine_id' => $data['site_origine_id'] ?? null,
            'structure_destination_id' => $data['structure_destination_id'],
            'patient_id' => $data['patient_id'],
            'praticien_referent_id' => $data['praticien_referent_id'],
            'motif' => $data['motif'],
            'statut' => 'envoye',
        ])->refresh();

        return (new PatientReferralResource($referral))->response()->setStatusCode(201);
    }

    public function accept(Request $request, PatientReferral $referral): PatientReferralResource
    {
        $this->abortUnlessDestination($request, $referral);

        $referral->update(['statut' => 'accepte']);

        $this->auditSiNonOrigine($request, $referral, 'acceptation');

        ReferencementAccepte::dispatch($referral);

        return new PatientReferralResource($referral);
    }

    public function refuse(Request $request, PatientReferral $referral): PatientReferralResource
    {
        $this->abortUnlessDestination($request, $referral);

        $referral->update(['statut' => 'refuse']);

        $this->auditSiNonOrigine($request, $referral, 'refus');

        ReferencementRefuse::dispatch($referral);

        return new PatientReferralResource($referral);
    }

    public function completeWithReport(Request $request, PatientReferral $referral): PatientReferralResource
    {
        $this->abortUnlessDestination($request, $referral);

        $data = $request->validate([
            'compte_rendu_retour' => ['required', 'string'],
        ]);

        $referral->update([
            'compte_rendu_retour' => $data['compte_rendu_retour'],
            'statut' => 'complete',
        ]);

        $this->auditSiNonOrigine($request, $referral, 'contre_reference');

        return new PatientReferralResource($referral);
    }

    /**
     * Résumé minimal du patient référencé, résolu explicitement en dehors
     * de son TenantScope naturel — borné à ce seul chemin, après
     * confirmation que la structure courante est bien partie prenante du
     * référencement (garanti par ReferralVisibilityScope sur $referral).
     */
    public function patientResume(Request $request, PatientReferral $referral): JsonResponse
    {
        $this->auditSiNonOrigine($request, $referral, 'consultation');

        $patient = Patient::withoutGlobalScope(TenantScope::class)->findOrFail($referral->patient_id);

        return response()->json([
            'nom' => trim($patient->first_name.' '.$patient->last_name),
            'date_naissance' => $patient->birth_date,
            'numero_patient' => $patient->patient_number,
        ]);
    }

    private function abortUnlessDestination(Request $request, PatientReferral $referral): void
    {
        abort_unless($request->user()->structure_id === $referral->structure_destination_id, 403, "Seule la structure destinataire peut effectuer cette action.");
    }

    private function auditSiNonOrigine(Request $request, PatientReferral $referral, string $action): void
    {
        if ($request->user()->structure_id === $referral->structure_origine_id) {
            return;
        }

        activity('partage_inter_structure')
            ->causedBy($request->user())
            ->performedOn($referral)
            ->withProperties([
                'structure_agissante_id' => $request->user()->structure_id,
                'structure_origine_id' => $referral->structure_origine_id,
                'structure_destination_id' => $referral->structure_destination_id,
                'patient_id' => $referral->patient_id,
                'action' => $action,
            ])
            ->log('Accès inter-structure sur un référencement patient');
    }
}
