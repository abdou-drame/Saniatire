<?php

namespace App\Http\Controllers\Api;

use App\Domain\Facturation\Models\BillableItem;
use App\Domain\Facturation\Models\Invoice;
use App\Domain\Patient\Models\Patient;
use App\Domain\Shared\Billing\InsuranceCoverageService;
use App\Http\Controllers\Controller;
use App\Http\Requests\InvoiceRequest;
use App\Http\Resources\InvoiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:facturation.view', only: ['index', 'show']),
            new Middleware('permission:facturation.create', only: ['store']),
            new Middleware('permission:facturation.validate', only: ['emit']),
            new Middleware('permission:facturation.cancel', only: ['cancel']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()
            ->with(['patient:id,first_name,last_name,patient_number', 'site:id,name', 'insuranceConvention:id,nom'])
            ->orderByDesc('id');

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->integer('patient_id'));
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }

        return InvoiceResource::collection($query->paginate())->response();
    }

    /**
     * Génère une facture à partir de BillableItem déjà produits
     * automatiquement par les modules cliniques (§1 : aucun montant n'est
     * ressaisi ici). Pour chaque ligne, la répartition assurance/patient
     * est calculée par InsuranceCoverageService (§2) et les BillableItem
     * consommés sont marqués "facturee" pour ne jamais être refacturés.
     */
    public function store(InvoiceRequest $request): JsonResponse
    {
        $data = $request->validated();

        $invoice = DB::transaction(function () use ($data) {
            $items = BillableItem::whereIn('id', $data['billable_item_ids'])
                ->where('patient_id', $data['patient_id'])
                ->where('statut', 'a_facturer')
                ->lockForUpdate()
                ->get();

            abort_if(
                $items->count() !== count($data['billable_item_ids']),
                422,
                'Certaines prestations sont introuvables ou déjà facturées.'
            );

            $patient = Patient::findOrFail($data['patient_id']);
            $now = now();

            $invoice = Invoice::create([
                'site_id' => $data['site_id'],
                'patient_id' => $data['patient_id'],
                'date_emission' => $now->toDateString(),
                'statut' => 'brouillon',
            ]);

            $montantTotal = 0.0;
            $montantPatient = 0.0;
            $montantAssurance = 0.0;
            $conventionId = null;

            foreach ($items as $item) {
                $split = app(InsuranceCoverageService::class)->computeSplit(
                    $patient,
                    (float) $item->montant_total,
                    $item->categorie,
                    $now
                );

                $conventionId = $split['convention']?->id ?? $conventionId;

                $invoice->items()->create([
                    'billable_item_id' => $item->id,
                    'libelle' => $item->libelle,
                    'categorie' => $item->categorie,
                    'quantite' => $item->quantite,
                    'prix_unitaire' => $item->prix_unitaire,
                    'montant_total' => $item->montant_total,
                    'taux_couverture_applique' => $split['taux'],
                    'montant_assurance' => $split['montant_assurance'],
                    'montant_patient' => $split['montant_patient'],
                ]);

                $montantTotal += (float) $item->montant_total;
                $montantPatient += $split['montant_patient'];
                $montantAssurance += $split['montant_assurance'];

                $item->update(['statut' => 'facturee']);
            }

            $invoice->update([
                'insurance_convention_id' => $conventionId,
                'montant_total' => round($montantTotal, 2),
                'montant_part_patient' => round($montantPatient, 2),
                'montant_part_assurance' => round($montantAssurance, 2),
            ]);

            return $invoice;
        });

        return (new InvoiceResource($invoice->load('items')))->response()->setStatusCode(201);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load([
            'items',
            'payments',
            'patient:id,first_name,last_name,patient_number',
            'site:id,name',
            'insuranceConvention:id,nom',
        ]));
    }

    public function emit(Invoice $invoice): InvoiceResource
    {
        abort_if($invoice->statut !== 'brouillon', 422, 'Seule une facture en brouillon peut être émise.');

        $invoice->update(['statut' => 'emise']);

        return new InvoiceResource($invoice);
    }

    /**
     * Libère les BillableItem consommés (redeviennent "a_facturer") afin
     * qu'une nouvelle facture puisse les reprendre.
     */
    public function cancel(Invoice $invoice): InvoiceResource
    {
        abort_if(in_array($invoice->statut, ['payee', 'annulee'], true), 422, 'Cette facture ne peut plus être annulée.');

        DB::transaction(function () use ($invoice) {
            $billableItemIds = $invoice->items()->whereNotNull('billable_item_id')->pluck('billable_item_id');
            BillableItem::whereIn('id', $billableItemIds)->update(['statut' => 'a_facturer']);
            $invoice->update(['statut' => 'annulee']);
        });

        return new InvoiceResource($invoice);
    }
}
