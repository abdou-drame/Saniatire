<?php

namespace App\Domain\Fhir\Converters;

use App\Domain\Fhir\Converters\Concerns\StripsEmptyFhirFields;
use App\Domain\Imagerie\Models\ImagingOrder;
use App\Domain\Laboratoire\Models\LabOrder;

/**
 * LabOrder et ImagingOrder internes -> FHIR R4 ServiceRequest, distingués
 * par un id préfixé (`lab-{id}` / `imaging-{id}`) — même convention que
 * DiagnosticReportFhirConverter. status, intent et subject sont 1..1
 * (obligatoires) ; intent est fixé à "order" (aucune notion de plan/proposal
 * côté interne).
 */
class ServiceRequestFhirConverter
{
    use StripsEmptyFhirFields;

    public static function fromLabOrder(LabOrder $order): array
    {
        return self::clean([
            'resourceType' => 'ServiceRequest',
            'id' => "lab-{$order->id}",
            'status' => match ($order->status) {
                'annule' => 'revoked',
                'transmis' => 'completed',
                default => 'active',
            },
            'intent' => 'order',
            'category' => [[
                'coding' => [[
                    'system' => 'http://snomed.info/sct',
                    'code' => '108252007',
                    'display' => 'Laboratory procedure',
                ]],
            ]],
            'code' => [
                'coding' => $order->items->map(fn ($item) => $item->loincCode ? [
                    'system' => 'http://loinc.org',
                    'code' => $item->loincCode->code,
                    'display' => $item->loincCode->label,
                ] : null)->filter()->values()->all(),
            ],
            'subject' => ['reference' => "Patient/{$order->patient_id}"],
            'encounter' => $order->consultation_id ? ['reference' => "Encounter/{$order->consultation_id}"] : null,
            'requester' => self::requesterReference($order->requester_type, $order->requester_id),
            'authoredOn' => $order->ordered_at?->toIso8601String(),
        ]);
    }

    public static function fromImagingOrder(ImagingOrder $order): array
    {
        return self::clean([
            'resourceType' => 'ServiceRequest',
            'id' => "imaging-{$order->id}",
            'status' => match ($order->status) {
                'annule' => 'revoked',
                'transmis' => 'completed',
                default => 'active',
            },
            'intent' => 'order',
            'category' => [[
                'coding' => [[
                    'system' => 'http://snomed.info/sct',
                    'code' => '363679005',
                    'display' => 'Imaging procedure',
                ]],
            ]],
            'code' => [
                'coding' => [[
                    'system' => 'urn:sanitaire:exam-type',
                    'code' => $order->exam_type,
                ]],
                'text' => $order->exam_type,
            ],
            'subject' => ['reference' => "Patient/{$order->patient_id}"],
            'encounter' => $order->consultation_id ? ['reference' => "Encounter/{$order->consultation_id}"] : null,
            'requester' => self::requesterReference($order->requester_type, $order->requester_id),
            'authoredOn' => $order->ordered_at?->toIso8601String(),
        ]);
    }

    private static function requesterReference(?string $requesterType, ?int $requesterId): ?array
    {
        if (! $requesterType || ! $requesterId) {
            return null;
        }

        // requester_type est polymorphe (User interne ou ExternalPrescriber,
        // étape 7b) : les deux sont représentés comme Practitioner côté
        // FHIR, la distinction interne restant dans l'identifiant local.
        return ['reference' => "Practitioner/{$requesterId}"];
    }
}
