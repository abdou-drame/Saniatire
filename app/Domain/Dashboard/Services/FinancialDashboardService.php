<?php

namespace App\Domain\Dashboard\Services;

use App\Domain\Caisse\Models\Payment;
use App\Domain\Facturation\Models\BillableItem;
use App\Domain\Facturation\Models\Invoice;
use Illuminate\Support\Carbon;

/**
 * "Chiffre d'affaires" = encaissements (Payment.montant sur la période),
 * jamais le montant facturé — voir décision 2 du plan d'étape 8. Le montant
 * facturé (Invoice.montant_total) reste utilisé séparément pour le taux de
 * recouvrement et la répartition par prestation.
 */
class FinancialDashboardService
{
    private function paidPayments($query)
    {
        // Un paiement mobile_money "failed" n'est pas un encaissement réel ;
        // statut_mobile_money est nul pour tous les autres modes.
        return $query->where(fn ($q) => $q->whereNull('statut_mobile_money')->orWhere('statut_mobile_money', 'confirmed'));
    }

    /**
     * @return array<int,array{site_id:?int,total:float}>
     */
    public function encaissementsParSite(Carbon $from, Carbon $to): array
    {
        $query = $this->paidPayments(
            Payment::query()->whereBetween('paid_at', [$from, $to])
        );

        return $query
            ->selectRaw('site_id, sum(montant) as total')
            ->groupBy('site_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['site_id' => $row->site_id, 'total' => round((float) $row->total, 2)])
            ->values()
            ->all();
    }

    /**
     * @return array<int,array{mode_paiement:string,total:float}>
     */
    public function encaissementsParModePaiement(?int $siteId, Carbon $from, Carbon $to): array
    {
        $query = $this->paidPayments(
            Payment::query()
                ->whereBetween('paid_at', [$from, $to])
                ->when($siteId, fn ($q, $id) => $q->where('site_id', $id))
        );

        return $query
            ->selectRaw('mode_paiement, sum(montant) as total')
            ->groupBy('mode_paiement')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['mode_paiement' => $row->mode_paiement, 'total' => round((float) $row->total, 2)])
            ->values()
            ->all();
    }

    public function totalEncaissements(?int $siteId, Carbon $from, Carbon $to): float
    {
        $query = $this->paidPayments(
            Payment::query()
                ->whereBetween('paid_at', [$from, $to])
                ->when($siteId, fn ($q, $id) => $q->where('site_id', $id))
        );

        return round((float) $query->sum('montant'), 2);
    }

    /**
     * Pas de filtre site (billable_items n'a pas de colonne site_id).
     *
     * @return array<int,array{categorie:string,montant_total:float}>
     */
    public function recettesFactureesParPrestation(Carbon $from, Carbon $to): array
    {
        return BillableItem::query()
            ->where('statut', '!=', 'annulee')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('categorie, sum(montant_total) as montant_total')
            ->groupBy('categorie')
            ->orderByDesc('montant_total')
            ->get()
            ->map(fn ($row) => ['categorie' => $row->categorie, 'montant_total' => round((float) $row->montant_total, 2)])
            ->values()
            ->all();
    }

    /**
     * Encaissé / facturé sur la période, arrondi à 2 décimales. `null` si
     * rien n'a été facturé sur la période (dénominateur nul).
     */
    public function tauxRecouvrement(?int $siteId, Carbon $from, Carbon $to): ?float
    {
        $facture = Invoice::query()
            ->whereBetween('date_emission', [$from, $to])
            ->whereNotIn('statut', ['brouillon', 'annulee'])
            ->when($siteId, fn ($q, $id) => $q->where('site_id', $id))
            ->sum('montant_total');

        if ((float) $facture <= 0) {
            return null;
        }

        $encaisse = $this->paidPayments(
            Payment::query()
                ->whereBetween('paid_at', [$from, $to])
                ->whereHas('invoice', fn ($q) => $q->where('statut', '!=', 'annulee'))
                ->when($siteId, fn ($q, $id) => $q->where('site_id', $id))
        )->sum('montant');

        return round(((float) $encaisse) / ((float) $facture), 2);
    }

    /**
     * Répartition de la part assureur vs part patient sur les factures
     * émises pendant la période — basée sur Invoice.montant_part_assurance/
     * montant_part_patient, pas sur les paiements.
     *
     * @return array{par_assureur: array<int,array{insurance_provider_id:?int,nom:?string,montant:float}>, part_patient:float}
     */
    public function repartitionAssureurPatient(?int $siteId, Carbon $from, Carbon $to): array
    {
        $query = Invoice::query()
            ->whereBetween('date_emission', [$from, $to])
            ->where('statut', '!=', 'annulee')
            ->when($siteId, fn ($q, $id) => $q->where('site_id', $id));

        $parAssureur = (clone $query)
            ->whereNotNull('insurance_convention_id')
            ->join('insurance_conventions', 'insurance_conventions.id', '=', 'invoices.insurance_convention_id')
            ->join('insurance_providers', 'insurance_providers.id', '=', 'insurance_conventions.insurance_provider_id')
            ->selectRaw('insurance_providers.id as insurance_provider_id, insurance_providers.nom as nom, sum(invoices.montant_part_assurance) as montant')
            ->groupBy('insurance_providers.id', 'insurance_providers.nom')
            ->orderByDesc('montant')
            ->get()
            ->map(fn ($row) => [
                'insurance_provider_id' => $row->insurance_provider_id,
                'nom' => $row->nom,
                'montant' => round((float) $row->montant, 2),
            ])
            ->values()
            ->all();

        $partPatient = round((float) (clone $query)->sum('montant_part_patient'), 2);

        return [
            'par_assureur' => $parAssureur,
            'part_patient' => $partPatient,
        ];
    }
}
