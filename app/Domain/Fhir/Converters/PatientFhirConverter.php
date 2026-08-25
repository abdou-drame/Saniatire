<?php

namespace App\Domain\Fhir\Converters;

use App\Domain\Fhir\Converters\Concerns\StripsEmptyFhirFields;
use App\Domain\Patient\Models\Patient;

/**
 * Patient interne -> FHIR R4 Patient. Voir app/Domain/Fhir/README.md pour
 * la table de correspondance complète et les limites connues (ex. address
 * est un champ texte libre côté interne, pas une adresse structurée FHIR).
 */
class PatientFhirConverter
{
    use StripsEmptyFhirFields;

    public static function convert(Patient $patient): array
    {
        return self::clean([
            'resourceType' => 'Patient',
            'id' => (string) $patient->id,
            'identifier' => [
                [
                    'system' => 'urn:sanitaire:patient-number',
                    'value' => $patient->patient_number,
                ],
            ],
            'active' => true,
            'name' => [[
                'use' => 'official',
                'family' => $patient->last_name,
                'given' => array_values(array_filter([$patient->first_name])),
            ]],
            'gender' => match ($patient->sex) {
                'M' => 'male',
                'F' => 'female',
                default => 'unknown',
            },
            'birthDate' => $patient->birth_date?->toDateString(),
            'telecom' => array_values(array_filter([
                $patient->phone ? ['system' => 'phone', 'value' => $patient->phone] : null,
                $patient->email ? ['system' => 'email', 'value' => $patient->email] : null,
            ])),
            // Champ libre côté interne (pas de line/city/postalCode
            // distincts) : mappé sur address[].text uniquement.
            'address' => $patient->address ? [['text' => $patient->address]] : [],
            'contact' => $patient->emergency_contact_name ? [[
                'relationship' => $patient->emergency_contact_relationship
                    ? [['text' => $patient->emergency_contact_relationship]]
                    : [],
                'name' => ['text' => $patient->emergency_contact_name],
                'telecom' => $patient->emergency_contact_phone
                    ? [['system' => 'phone', 'value' => $patient->emergency_contact_phone]]
                    : [],
            ]] : [],
        ]);
    }
}
