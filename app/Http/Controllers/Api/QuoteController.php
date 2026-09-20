<?php

namespace App\Http\Controllers\Api;

use App\Domain\Facturation\Models\Invoice;
use App\Domain\Facturation\Models\Quote;
use App\Domain\Shared\Billing\InsuranceCoverageService;
use App\Http\Controllers\Controller;
use App\Http\Requests\QuoteRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\QuoteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class QuoteController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:facturation.view', only: ['index', 'show']),
            new Middleware('permission:facturation.create', only: ['store']),
            new Middleware('permission:facturation.cancel', only: ['cancel']),
            new Middleware('permission:facturation.validate', only: ['convert']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = Quote::query()
            ->with(['patient:id,first_name,last_name,patient_number', 'site:id,name'])
            ->orderByDesc('id');

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->integer('patient_id'));
        }

        return QuoteResource::collection($query->paginate())->response();
    }

    /**
     * Un devis est chiffré manuellement (pas encore de BillableItem tant
     * que l'acte n'est pas réalisé), contrairement à la facture qui
     * consomme des BillableItem déjà produits.
     */
    public function store(QuoteRequest $request): JsonResponse
    {
        $data = $request->validated();

        $quote = DB::transaction(function () use ($data) {
            $quote = Quote::create([
                'site_id' => $data['site_id'],
                'patient_id' => $data['patient_id'],
                'date_emission' => now()->toDateString(),
                'statut' => 'brouillon',
            ]);

            $montantTotal = 0.0;

            foreach ($data['items'] as $itemData) {
                $montant = round($itemData['prix_unitaire'] * $itemData['quantite'], 2);

                $quote->items()->create([
                    'libelle' => $itemData['libelle'],
                    'categorie' => $itemData['categorie'],
                    'quantite' => $itemData['quantite'],
                    'prix_unitaire' => $itemData['prix_unitaire'],
                    'montant_total' => $montant,
                ]);

                $montantTotal += $montant;
            }

            $quote->update([
                'montant_total' => round($montantTotal, 2),
                'statut' => 'emis',
            ]);

            return $quote;
        });

        return (new QuoteResource($quote->load('items')))->response()->setStatusCode(201);
    }

    public function show(Quote $quote): QuoteResource
    {
        return new QuoteResource($quote->load(['items', 'patient:id,first_name,last_name,patient_number', 'site:id,name']));
    }

    public function cancel(Quote $quote): QuoteResource
    {
        abort_if(in_array($quote->statut, ['converti', 'annule'], true), 422, 'Ce devis ne peut plus être annulé.');

        $quote->update(['statut' => 'annule']);

        return new QuoteResource($quote);
    }

    /**
     * Conversion en facture : la répartition assurance/patient est
     * recalculée à la date de conversion (pas une copie des montants du
     * devis), car la couverture du patient a pu changer entre-temps.
     */
    public function convert(Quote $quote): JsonResponse
    {
        abort_if($quote->statut !== 'emis', 422, 'Seul un devis émis peut être converti en facture.');

        $invoice = DB::transaction(function () use ($quote) {
            $patient = $quote->patient;
            $now = now();

            $invoice = Invoice::create([
                'site_id' => $quote->site_id,
                'patient_id' => $quote->patient_id,
                'date_emission' => $now->toDateString(),
                'statut' => 'brouillon',
            ]);

            $montantTotal = 0.0;
            $montantPatient = 0.0;
            $montantAssurance = 0.0;
            $conventionId = null;

            foreach ($quote->items as $item) {
                $split = app(InsuranceCoverageService::class)->computeSplit(
                    $patient,
                    (float) $item->montant_total,
                    $item->categorie,
                    $now
                );

                $conventionId = $split['convention']?->id ?? $conventionId;

                $invoice->items()->create([
                    'billable_item_id' => $item->billable_item_id,
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
            }

            $invoice->update([
                'insurance_convention_id' => $conventionId,
                'montant_total' => round($montantTotal, 2),
                'montant_part_patient' => round($montantPatient, 2),
                'montant_part_assurance' => round($montantAssurance, 2),
            ]);

            $quote->update([
                'statut' => 'converti',
                'converted_invoice_id' => $invoice->id,
            ]);

            return $invoice;
        });

        return (new InvoiceResource($invoice->load('items')))->response()->setStatusCode(201);
    }
}
