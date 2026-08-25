<?php

namespace App\Domain\Shared\Billing;

use App\Domain\Assurance\Models\InsuranceConvention;
use App\Domain\Assurance\Models\PatientInsuranceCoverage;
use App\Domain\Patient\Models\Patient;
use DateTimeInterface;

class InsuranceCoverageService
{
    /**
     * Cœur métier de l'étape 5b (§2 cahier des charges) : répartit un
     * montant entre la part organisme assureur et la part patient.
     *
     * 1. Pas de couverture active du patient à la date donnée -> 100% part
     *    patient.
     * 2. Couverture active mais pas de règle pour cette catégorie, ou règle
     *    explicitement exclue -> 0% part assurance (acte exclu).
     * 3. Sinon montant_assurance = montant_total * taux_couverture / 100,
     *    plafonné par plafond_montant si défini ; le reste est part
     *    patient.
     *
     * @return array{convention: ?InsuranceConvention, taux: float, montant_assurance: float, montant_patient: float}
     */
    public function computeSplit(Patient $patient, float $montantTotal, string $categorie, DateTimeInterface $date): array
    {
        $coverage = PatientInsuranceCoverage::query()
            ->where('patient_id', $patient->id)
            ->where('actif', true)
            ->where('date_debut', '<=', $date)
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>=', $date))
            ->whereHas('convention', fn ($q) => $q->where('actif', true)
                ->where('date_debut', '<=', $date)
                ->where(fn ($q2) => $q2->whereNull('date_fin')->orWhere('date_fin', '>=', $date)))
            ->with('convention')
            ->first();

        if (! $coverage) {
            return [
                'convention' => null,
                'taux' => 0.0,
                'montant_assurance' => 0.0,
                'montant_patient' => round($montantTotal, 2),
            ];
        }

        $convention = $coverage->convention;
        $rule = $convention->coverageRules()->where('categorie', $categorie)->first();

        if (! $rule || $rule->exclu) {
            return [
                'convention' => $convention,
                'taux' => 0.0,
                'montant_assurance' => 0.0,
                'montant_patient' => round($montantTotal, 2),
            ];
        }

        $montantAssurance = round($montantTotal * ((float) $rule->taux_couverture) / 100, 2);

        if ($rule->plafond_montant !== null) {
            $montantAssurance = min($montantAssurance, (float) $rule->plafond_montant);
        }

        $montantAssurance = min($montantAssurance, $montantTotal);
        $montantPatient = round($montantTotal - $montantAssurance, 2);

        return [
            'convention' => $convention,
            'taux' => (float) $rule->taux_couverture,
            'montant_assurance' => $montantAssurance,
            'montant_patient' => $montantPatient,
        ];
    }
}
