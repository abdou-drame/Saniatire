<?php

namespace App\Domain\Fhir\Converters;

use App\Domain\Fhir\Converters\Concerns\StripsEmptyFhirFields;
use App\Domain\Imagerie\Models\ImagingReport;
use App\Domain\Laboratoire\Models\LabOrder;

/**
 * Deux sources internes distinctes alimentent FHIR DiagnosticReport —
 * ImagingReport (un CR de radiologie) et LabOrder (regroupement des
 * résultats validés d'une demande de labo, FHIR n'ayant pas d'équivalent
 * "LabOrder" séparé de son rapport). Pour les distinguer côté route/id
 * FHIR, l'id de ressource est préfixé (`imaging-{id}` / `lab-{id}`) — voir
 * app/Domain/Fhir/README.md. status et code sont 1..1 (obligatoires).
 */
class DiagnosticReportFhirConverter
{
    use StripsEmptyFhirFields;

    public static function fromImagingReport(ImagingReport $report): array
    {
        $study = $report->study;
        $order = $study?->imagingOrder;

        return self::clean([
            'resourceType' => 'DiagnosticReport',
            'id' => "imaging-{$report->id}",
            'status' => match ($report->status) {
                'valide' => 'final',
                default => 'preliminary',
            },
            'category' => [[
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/v2-0074',
                    'code' => 'RAD',
                ]],
            ]],
            'code' => [
                'coding' => [[
                    'system' => 'urn:sanitaire:exam-type',
                    'code' => $order?->exam_type,
                ]],
                'text' => $order?->exam_type,
            ],
            'subject' => $order ? ['reference' => "Patient/{$order->patient_id}"] : null,
            'encounter' => $order?->consultation_id ? ['reference' => "Encounter/{$order->consultation_id}"] : null,
            'effectiveDateTime' => $study?->performed_at?->toIso8601String(),
            'issued' => $report->validated_at?->toIso8601String(),
            'performer' => $report->author_id ? [['reference' => "Practitioner/{$report->author_id}"]] : [],
            'conclusion' => $report->content,
        ]);
    }

    public static function fromLabOrder(LabOrder $order): array
    {
        $observationRefs = $order->items
            ->filter(fn ($item) => $item->result !== null)
            ->map(fn ($item) => ['reference' => "Observation/{$item->result->id}"])
            ->values()
            ->all();

        return self::clean([
            'resourceType' => 'DiagnosticReport',
            'id' => "lab-{$order->id}",
            'status' => match ($order->status) {
                'transmis' => 'final',
                'resultats_disponibles', 'en_analyse' => 'partial',
                'annule' => 'cancelled',
                default => 'registered',
            },
            'category' => [[
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/v2-0074',
                    'code' => 'LAB',
                ]],
            ]],
            'code' => [
                'coding' => [[
                    'system' => 'urn:sanitaire:report-type',
                    'code' => 'laboratoire',
                ]],
                'text' => 'Analyses de laboratoire',
            ],
            'subject' => ['reference' => "Patient/{$order->patient_id}"],
            'encounter' => $order->consultation_id ? ['reference' => "Encounter/{$order->consultation_id}"] : null,
            'effectiveDateTime' => $order->ordered_at?->toIso8601String(),
            'result' => $observationRefs,
        ]);
    }
}
