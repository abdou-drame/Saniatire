<?php

namespace App\Domain\Shared\Billing;

/**
 * Contract implemented by every clinical module's model whose completion
 * should trigger an automatic billing line (cahier des charges §48 :
 * facturation à partir des prestations réellement réalisées, pas de
 * ressaisie manuelle). Mirrors App\Domain\Shared\Specialty\SpecialtyRecord:
 * a single BillingService::recordService() call handles every implementor
 * uniformly, so the billing logic never has to be duplicated per module.
 */
interface Billable
{
    public function billingPatientId(): int;

    /**
     * Shared vocabulary with insurance_convention_coverage_rules.categorie
     * (e.g. 'consultation', 'laboratoire', 'imagerie', 'hospitalisation',
     * 'chirurgie', 'dialyse', 'kinesitherapie').
     */
    public function billingCategorie(): string;

    public function billingLibelle(): string;

    /**
     * Lookup key in service_tariffs.code for this structure.
     */
    public function billingTariffCode(): string;

    /**
     * 1 for most acts; e.g. number of nights for a hospitalization.
     */
    public function billingQuantite(): int;
}
