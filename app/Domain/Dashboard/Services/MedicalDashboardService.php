<?php

namespace App\Domain\Dashboard\Services;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Facturation\Models\BillableItem;
use Illuminate\Support\Carbon;

/**
 * Requêtes simples, sans état ni cache interne (le cache s'applique dans
 * les contrôleurs — voir app/Domain/Dashboard/README.md). Une méthode par
 * indicateur pour rester testable isolément.
 */
class MedicalDashboardService
{
    /**
     * @return array{total:int, par_praticien: array<int,array{practitioner_id:int,total:int}>, par_specialite: array<int,array{specialty_type:?string,total:int}>}
     */
    public function consultationsSummary(?int $siteId, Carbon $from, Carbon $to): array
    {
        $query = Consultation::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($siteId, fn ($q, $id) => $q->where('site_id', $id));

        $total = (clone $query)->count();

        $parPraticien = (clone $query)
            ->selectRaw('practitioner_id, count(*) as total')
            ->groupBy('practitioner_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['practitioner_id' => $row->practitioner_id, 'total' => (int) $row->total])
            ->values()
            ->all();

        $parSpecialite = (clone $query)
            ->selectRaw('specialty_type, count(*) as total')
            ->groupBy('specialty_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['specialty_type' => $row->specialty_type, 'total' => (int) $row->total])
            ->values()
            ->all();

        return [
            'total' => $total,
            'par_praticien' => $parPraticien,
            'par_specialite' => $parSpecialite,
        ];
    }

    /**
     * Reconstruit à partir de billable_items (pas de table dédiée par
     * spécialité) — aucune colonne site_id sur cette table, donc cet
     * indicateur reste structure-wide, jamais filtrable par site (voir
     * README).
     *
     * @return array<int,array{categorie:string,total_actes:int,montant_total:float}>
     */
    public function actesParSpecialite(Carbon $from, Carbon $to): array
    {
        return BillableItem::query()
            ->where('statut', '!=', 'annulee')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('categorie, count(*) as total_actes, sum(montant_total) as montant_total')
            ->groupBy('categorie')
            ->orderByDesc('montant_total')
            ->get()
            ->map(fn ($row) => [
                'categorie' => $row->categorie,
                'total_actes' => (int) $row->total_actes,
                'montant_total' => round((float) $row->montant_total, 2),
            ])
            ->values()
            ->all();
    }
}
