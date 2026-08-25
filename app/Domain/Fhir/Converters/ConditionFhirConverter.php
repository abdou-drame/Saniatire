<?php

namespace App\Domain\Fhir\Converters;

use App\Domain\Consultation\Models\ConsultationDiagnosis;
use App\Domain\Fhir\Converters\Concerns\StripsEmptyFhirFields;

/**
 * ConsultationDiagnosis interne -> FHIR R4 Condition. subject est 1..1
 * (obligatoire) — nécessite consultation.patient_id, donc le contrôleur
 * doit charger la relation consultation avant d'appeler convert().
 */
class ConditionFhirConverter
{
    use StripsEmptyFhirFields;

    public static function convert(ConsultationDiagnosis $diagnosis): array
    {
        return self::clean([
            'resourceType' => 'Condition',
            'id' => (string) $diagnosis->id,
            'clinicalStatus' => [
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                    'code' => 'active',
                ]],
            ],
            'verificationStatus' => [
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/condition-ver-status',
                    'code' => match ($diagnosis->status) {
                        'confirme' => 'confirmed',
                        default => 'provisional',
                    },
                ]],
            ],
            'category' => [[
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/condition-category',
                    'code' => $diagnosis->type === 'principal' ? 'encounter-diagnosis' : 'problem-list-item',
                ]],
            ]],
            'code' => [
                'coding' => [[
                    'system' => $diagnosis->version_snapshot === 'CIM-11'
                        ? 'urn:sanitaire:cim-11'
                        : 'urn:sanitaire:cim-10',
                    'code' => $diagnosis->code_snapshot,
                    'display' => $diagnosis->label_snapshot,
                ]],
                'text' => $diagnosis->label_snapshot,
            ],
            'subject' => [
                'reference' => "Patient/{$diagnosis->consultation->patient_id}",
            ],
            'encounter' => [
                'reference' => "Encounter/{$diagnosis->consultation_id}",
            ],
        ]);
    }
}
