<?php

namespace App\Domain\Fhir\Converters;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Fhir\Converters\Concerns\StripsEmptyFhirFields;

/**
 * Consultation interne -> FHIR R4 Encounter. status et class sont 1..1
 * (obligatoires) — voir app/Domain/Fhir/README.md.
 */
class EncounterFhirConverter
{
    use StripsEmptyFhirFields;

    public static function convert(Consultation $consultation): array
    {
        return self::clean([
            'resourceType' => 'Encounter',
            'id' => (string) $consultation->id,
            'status' => match ($consultation->status) {
                'en_cours' => 'in-progress',
                'terminee' => 'finished',
                default => 'unknown',
            },
            'class' => [
                'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                'code' => 'AMB',
                'display' => 'ambulatory',
            ],
            'serviceType' => $consultation->specialty_type ? [
                'coding' => [[
                    'system' => 'urn:sanitaire:specialty-type',
                    'code' => $consultation->specialty_type,
                ]],
            ] : null,
            'subject' => [
                'reference' => "Patient/{$consultation->patient_id}",
            ],
            'participant' => $consultation->practitioner_id ? [[
                'individual' => ['reference' => "Practitioner/{$consultation->practitioner_id}"],
            ]] : [],
            'reasonCode' => $consultation->reason ? [['text' => $consultation->reason]] : [],
            'serviceProvider' => $consultation->site_id ? [
                'reference' => "Organization/{$consultation->site_id}",
            ] : null,
            'period' => array_filter([
                'start' => $consultation->created_at?->toIso8601String(),
                'end' => $consultation->closed_at?->toIso8601String(),
            ]),
        ]);
    }
}
