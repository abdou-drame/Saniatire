<?php

namespace App\Domain\Fhir\Converters;

use App\Domain\Fhir\Converters\Concerns\StripsEmptyFhirFields;
use App\Domain\Laboratoire\Models\LabResult;

/**
 * LabResult interne -> FHIR R4 Observation. status et code sont 1..1
 * (obligatoires). Le contrôleur doit précharger
 * sample.labOrder.patient et orderItem.loincCode avant convert().
 */
class ObservationFhirConverter
{
    use StripsEmptyFhirFields;

    public static function convert(LabResult $result): array
    {
        $loinc = $result->orderItem?->loincCode;
        $order = $result->sample?->labOrder;

        return self::clean([
            'resourceType' => 'Observation',
            'id' => (string) $result->id,
            'status' => match ($result->status) {
                'valide', 'transmis' => 'final',
                'en_cours', 'validation_technique_attente', 'validation_technique_faite', 'validation_biologique_attente' => 'preliminary',
                default => 'unknown',
            },
            'category' => [[
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
                    'code' => 'laboratory',
                ]],
            ]],
            'code' => [
                'coding' => $loinc ? [[
                    'system' => 'http://loinc.org',
                    'code' => $loinc->code,
                    'display' => $loinc->label,
                ]] : [],
                'text' => $loinc?->label,
            ],
            'subject' => $order ? ['reference' => "Patient/{$order->patient_id}"] : null,
            'encounter' => $order?->consultation_id ? ['reference' => "Encounter/{$order->consultation_id}"] : null,
            'valueQuantity' => is_numeric($result->value) ? [
                'value' => (float) $result->value,
                'unit' => $result->unit,
            ] : null,
            'valueString' => is_numeric($result->value) ? null : $result->value,
            'referenceRange' => ($result->reference_min !== null || $result->reference_max !== null) ? [[
                'low' => $result->reference_min !== null ? ['value' => (float) $result->reference_min] : null,
                'high' => $result->reference_max !== null ? ['value' => (float) $result->reference_max] : null,
            ]] : [],
            'interpretation' => $result->interpretation ? [[
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/v3-ObservationInterpretation',
                    'code' => match ($result->interpretation) {
                        'anormal' => 'A',
                        'critique' => 'AA',
                        default => 'N',
                    },
                ]],
            ]] : [],
        ]);
    }
}
