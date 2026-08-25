<?php

namespace App\Http\Controllers\Api;

use App\Domain\Imagerie\Models\ImagingOrder;
use App\Domain\Laboratoire\Models\LabOrder;
use App\Domain\Laboratoire\Models\LoincCode;
use App\Domain\Patient\Models\Patient;
use App\Domain\Prescripteur\Models\ExternalPrescriber;
use App\Domain\Structure\Models\Site;
use App\Http\Controllers\Controller;
use App\Http\Resources\ImagingOrderResource;
use App\Http\Resources\LabOrderResource;
use App\Http\Resources\LoincCodeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Étape 7b §3 : un prescripteur externe n'agit que pour sa propre structure
 * cliente (patient_id validé via Patient::findOrFail — TenantScope le
 * borne déjà automatiquement à la structure du guard prescriber, un patient
 * d'une autre structure produit un 404, jamais une fuite d'existence) et ne
 * voit que les demandes qu'il a lui-même faites (requester_type/_id).
 */
class PrescriberPortalController extends Controller
{
    public function storeDemandeLabo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer'],
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.loinc_code_id' => ['required', 'integer', 'exists:loinc_codes,id'],
        ]);

        $prescriber = $request->user();
        Patient::findOrFail($data['patient_id']);
        Site::findOrFail($data['site_id']);

        $order = LabOrder::create([
            'patient_id' => $data['patient_id'],
            'site_id' => $data['site_id'],
            'requester_type' => ExternalPrescriber::class,
            'requester_id' => $prescriber->id,
            'notes' => $data['notes'] ?? null,
            'ordered_at' => now(),
        ])->refresh();

        foreach ($data['items'] as $item) {
            $order->items()->create(['loinc_code_id' => $item['loinc_code_id']]);
        }

        return (new LabOrderResource($order->load('items.loincCode')))->response()->setStatusCode(201);
    }

    public function storeDemandeImagerie(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer'],
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'exam_type' => ['required', Rule::in(['radio', 'echo', 'scanner', 'irm'])],
            'notes' => ['nullable', 'string'],
        ]);

        $prescriber = $request->user();
        Patient::findOrFail($data['patient_id']);
        Site::findOrFail($data['site_id']);

        $order = ImagingOrder::create([
            'patient_id' => $data['patient_id'],
            'site_id' => $data['site_id'],
            'requester_type' => ExternalPrescriber::class,
            'requester_id' => $prescriber->id,
            'exam_type' => $data['exam_type'],
            'notes' => $data['notes'] ?? null,
            'ordered_at' => now(),
        ])->refresh();

        return (new ImagingOrderResource($order))->response()->setStatusCode(201);
    }

    /**
     * Sites actifs de la structure du prescripteur, pour le choix du lieu
     * de prélèvement/examen. Même raisonnement que
     * PatientPortalController::sites() : `/sites` (staff) exige une
     * permission Spatie qu'ExternalPrescriber n'a pas — cette route rend
     * seulement visible ce que TenantScope borne déjà à sa structure.
     */
    public function sites(): JsonResponse
    {
        $sites = Site::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => $sites]);
    }

    /**
     * Référentiel LOINC pour composer une demande labo. Catalogue partagé
     * entre structures et non sensible (voir LoincCodeController, déjà
     * exposé au staff via permission:laboratoire.view) — même recherche,
     * gardée ici par le guard prescripteur au lieu de cette permission.
     */
    public function loincCodes(Request $request): JsonResponse
    {
        $codes = LoincCode::query()
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request) {
                $term = '%'.mb_strtolower($request->string('search')->toString()).'%';
                $q->where(fn ($q) => $q->whereRaw('LOWER(code) LIKE ?', [$term])->orWhereRaw('LOWER(label) LIKE ?', [$term]));
            })
            ->orderBy('code')
            ->limit(50)
            ->get();

        return response()->json(['data' => LoincCodeResource::collection($codes)]);
    }

    /**
     * Recherche de patients existants dans la structure du prescripteur.
     * `search` est obligatoire (contrairement à PatientController::index
     * côté staff) et limitée à 15 résultats : un prescripteur externe ne
     * doit jamais pouvoir lister l'ensemble des patients de la structure
     * sans un terme précis. DTO minimal — jamais PatientResource complet,
     * ni téléphone, ni adresse, ni donnée médicale.
     */
    public function patients(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['required', 'string', 'min:2'],
        ]);

        $term = '%'.mb_strtolower($data['search']).'%';

        $patients = Patient::query()
            ->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(first_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(patient_number) LIKE ?', [$term]);
            })
            ->orderBy('last_name')
            ->limit(15)
            ->get(['id', 'first_name', 'last_name', 'patient_number', 'birth_date']);

        return response()->json(['data' => $patients]);
    }

    /**
     * Mes demandes de laboratoire, tous statuts confondus, pour le suivi
     * (en cours / résultat disponible / transmis). Remplace l'ancien
     * endpoint /resultats, qui ne renvoyait que le statut `transmis` sans
     * permettre de suivi des autres états.
     */
    public function demandesLabo(Request $request): JsonResponse
    {
        $prescriber = $request->user();

        $orders = LabOrder::query()
            ->where('requester_type', ExternalPrescriber::class)
            ->where('requester_id', $prescriber->id)
            ->with(['patient', 'site', 'items.loincCode', 'items.result'])
            ->orderByDesc('ordered_at')
            ->get();

        return response()->json(['data' => $orders->map(fn (LabOrder $order) => self::labOrderDto($order))]);
    }

    /**
     * Mes demandes d'imagerie, tous statuts confondus — même principe que
     * demandesLabo().
     */
    public function demandesImagerie(Request $request): JsonResponse
    {
        $prescriber = $request->user();

        $orders = ImagingOrder::query()
            ->where('requester_type', ExternalPrescriber::class)
            ->where('requester_id', $prescriber->id)
            ->with(['patient', 'site', 'studies.report'])
            ->orderByDesc('ordered_at')
            ->get();

        return response()->json(['data' => $orders->map(fn (ImagingOrder $order) => self::imagingOrderDto($order))]);
    }

    /**
     * Le contenu clinique (valeur, interprétation, texte du compte-rendu)
     * n'est inclus que lorsque status === 'transmis' : c'est la même
     * barrière de sécurité que l'ancien endpoint /resultats appliquait
     * déjà. Avant ce statut, seul le libellé de la demande est visible —
     * "résultat disponible" en interne ne veut pas dire "communiqué au
     * prescripteur externe".
     */
    private static function labOrderDto(LabOrder $order): array
    {
        $transmis = $order->status === 'transmis';

        return [
            'id' => $order->id,
            'status' => $order->status,
            'ordered_at' => $order->ordered_at?->toIso8601String(),
            'notes' => $order->notes,
            'site' => $order->site ? ['id' => $order->site->id, 'name' => $order->site->name] : null,
            'patient' => self::patientDto($order->patient),
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'label' => $item->loincCode?->label,
                'code' => $item->loincCode?->code,
                'value' => $transmis ? $item->result?->value : null,
                'unit' => $transmis ? $item->result?->unit : null,
                'interpretation' => $transmis ? $item->result?->interpretation : null,
            ]),
        ];
    }

    private static function imagingOrderDto(ImagingOrder $order): array
    {
        $transmis = $order->status === 'transmis';

        return [
            'id' => $order->id,
            'status' => $order->status,
            'exam_type' => $order->exam_type,
            'ordered_at' => $order->ordered_at?->toIso8601String(),
            'notes' => $order->notes,
            'site' => $order->site ? ['id' => $order->site->id, 'name' => $order->site->name] : null,
            'patient' => self::patientDto($order->patient),
            'studies' => $order->studies->map(fn ($study) => [
                'id' => $study->id,
                'modality' => $study->modality,
                'performed_at' => $study->performed_at?->toIso8601String(),
                'report' => $transmis ? $study->report?->content : null,
            ]),
        ];
    }

    private static function patientDto(?Patient $patient): ?array
    {
        if (! $patient) {
            return null;
        }

        return [
            'id' => $patient->id,
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'patient_number' => $patient->patient_number,
        ];
    }
}
