<?php

namespace App\Domain\Dashboard\Services;

use App\Domain\Qualite\Models\Complaint;
use App\Domain\Qualite\Models\PatientSatisfactionSurvey;
use Illuminate\Support\Carbon;

class QualityDashboardService
{
    public function scoreMoyenSatisfaction(?string $service, Carbon $from, Carbon $to): ?float
    {
        $query = PatientSatisfactionSurvey::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($service, fn ($q, $s) => $q->where('service', $s));

        $moyenne = $query->avg('note');

        return $moyenne === null ? null : round((float) $moyenne, 2);
    }

    /**
     * @return array{par_motif: array<int,array{motif:string,total:int}>, par_statut: array<int,array{statut:string,total:int}>}
     */
    public function repartitionReclamations(Carbon $from, Carbon $to): array
    {
        $query = Complaint::query()->whereBetween('created_at', [$from, $to]);

        $parMotif = (clone $query)
            ->selectRaw('motif, count(*) as total')
            ->groupBy('motif')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['motif' => $row->motif, 'total' => (int) $row->total])
            ->values()
            ->all();

        $parStatut = (clone $query)
            ->selectRaw('statut, count(*) as total')
            ->groupBy('statut')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['statut' => $row->statut, 'total' => (int) $row->total])
            ->values()
            ->all();

        return ['par_motif' => $parMotif, 'par_statut' => $parStatut];
    }

    /**
     * Moyenne (resolved_at - created_at) en heures, sur les réclamations
     * résolues (resolved_at non nul) pendant la période — une réclamation
     * encore ouverte n'entre pas dans la moyenne.
     */
    public function delaiMoyenResolutionHeures(Carbon $from, Carbon $to): ?float
    {
        $complaints = Complaint::query()
            ->whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [$from, $to])
            ->get(['created_at', 'resolved_at']);

        if ($complaints->isEmpty()) {
            return null;
        }

        return round($complaints->avg(fn (Complaint $c) => $c->created_at->diffInHours($c->resolved_at)), 1);
    }
}
