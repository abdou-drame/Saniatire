<?php

namespace App\Domain\Shared\Billing;

use App\Domain\Facturation\Models\BillableItem;
use App\Domain\Facturation\Models\ServiceTariff;

class BillingService
{
    /**
     * Single entry point called by every clinical controller/model once its
     * own act reaches completion. Resolves the applicable tariff for this
     * structure, creates the automatic billing line, and — generically,
     * without any per-module special-casing — flips billing_status to
     * 'done' when the source model carries that column.
     *
     * If no active tariff is configured for the code yet (an admin/finance
     * setup gap), a BillableItem is still created, with a zero amount and
     * statut 'a_tarifer' so the missing pricing is visible in Facturation
     * instead of the act leaving no billing trace at all. Clinical
     * workflows are never blocked by missing billing configuration.
     */
    public function recordService(Billable $model): ?BillableItem
    {
        $tariff = ServiceTariff::where('code', $model->billingTariffCode())
            ->where('actif', true)
            ->first();

        $quantite = max(1, $model->billingQuantite());

        $item = BillableItem::create([
            'structure_id' => $model->billingStructureId(),
            'patient_id' => $model->billingPatientId(),
            'billable_type' => get_class($model),
            'billable_id' => $model->getKey(),
            'categorie' => $model->billingCategorie(),
            'code_prestation' => $tariff->code ?? $model->billingTariffCode(),
            'libelle' => $model->billingLibelle(),
            'quantite' => $quantite,
            'prix_unitaire' => $tariff->prix_unitaire ?? 0,
            'montant_total' => $tariff ? round($tariff->prix_unitaire * $quantite, 2) : 0,
            'statut' => $tariff ? 'a_facturer' : 'a_tarifer',
        ]);

        $this->markBillingStatus($model);

        return $item;
    }

    private function markBillingStatus(Billable $model): void
    {
        if (array_key_exists('billing_status', $model->getAttributes())) {
            $model->update(['billing_status' => 'done']);
        }
    }
}
