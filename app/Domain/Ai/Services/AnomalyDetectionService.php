<?php

namespace App\Domain\Ai\Services;

use App\Domain\Consultation\Models\Consultation;
use App\Domain\Laboratoire\Models\LabOrder;

/**
 * Étape 9 §4 : détection d'anomalies — comparaison simple à des plages de
 * référence, pas un appel IA. Deux sources :
 *  - constantes vitales de la consultation, comparées à des plages adulte
 *    fixes (voir VITAL_RANGES) — limite explicite : aucun ajustement
 *    pédiatrique/gériatrique, à documenter dans app/Domain/Ai/README.md.
 *  - résultats de labo rattachés à la consultation, comparés à leurs
 *    propres reference_min/reference_max déjà en base (LabResult), recoupés
 *    avec le champ interpretation déjà saisi par le biologiste.
 * Ne modifie jamais rien : retourne uniquement une liste d'anomalies
 * détectées, à charge du praticien d'en tenir compte.
 */
class AnomalyDetectionService
{
    /**
     * @var array<string,array{min:float,max:float,unit:string,label:string}>
     */
    private const VITAL_RANGES = [
        'temperature_c' => ['min' => 36.1, 'max' => 37.2, 'unit' => '°C', 'label' => 'Température'],
        'heart_rate' => ['min' => 60, 'max' => 100, 'unit' => 'bpm', 'label' => 'Fréquence cardiaque'],
        'respiratory_rate' => ['min' => 12, 'max' => 20, 'unit' => '/min', 'label' => 'Fréquence respiratoire'],
        'spo2' => ['min' => 95, 'max' => 100, 'unit' => '%', 'label' => 'SpO2'],
        'blood_pressure_systolic' => ['min' => 90, 'max' => 140, 'unit' => 'mmHg', 'label' => 'Pression systolique'],
        'blood_pressure_diastolic' => ['min' => 60, 'max' => 90, 'unit' => 'mmHg', 'label' => 'Pression diastolique'],
    ];

    public function detectForConsultation(Consultation $consultation): array
    {
        return [
            'vitals' => $this->detectVitalAnomalies($consultation),
            'lab_results' => $this->detectLabAnomalies($consultation),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function detectVitalAnomalies(Consultation $consultation): array
    {
        $anomalies = [];

        foreach (self::VITAL_RANGES as $field => $range) {
            $value = $consultation->{$field};

            if ($value === null) {
                continue;
            }

            $value = (float) $value;

            if ($value < $range['min'] || $value > $range['max']) {
                $anomalies[] = [
                    'field' => $field,
                    'label' => $range['label'],
                    'value' => $value,
                    'unit' => $range['unit'],
                    'reference_min' => $range['min'],
                    'reference_max' => $range['max'],
                    'message' => "{$range['label']} hors plage de référence adulte ({$range['min']}-{$range['max']} {$range['unit']}) : {$value} {$range['unit']}.",
                ];
            }
        }

        return $anomalies;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function detectLabAnomalies(Consultation $consultation): array
    {
        $labResults = LabOrder::where('consultation_id', $consultation->id)
            ->with('items.result', 'items.loincCode')
            ->get()
            ->flatMap(fn (LabOrder $order) => $order->items)
            ->map(fn ($item) => [$item->result, $item->loincCode])
            ->filter(fn ($pair) => $pair[0] !== null);

        $anomalies = [];

        foreach ($labResults as [$result, $loinc]) {
            $isOutOfRange = is_numeric($result->value)
                && (
                    ($result->reference_min !== null && (float) $result->value < (float) $result->reference_min)
                    || ($result->reference_max !== null && (float) $result->value > (float) $result->reference_max)
                );

            $isFlagged = in_array($result->interpretation, ['anormal', 'critique'], true);

            if (! $isOutOfRange && ! $isFlagged) {
                continue;
            }

            $anomalies[] = [
                'lab_result_id' => $result->id,
                'label' => $loinc?->label,
                'value' => $result->value,
                'unit' => $result->unit,
                'reference_min' => $result->reference_min,
                'reference_max' => $result->reference_max,
                'interpretation' => $result->interpretation,
                'message' => $isFlagged
                    ? "Résultat marqué {$result->interpretation} par le biologiste."
                    : 'Valeur hors plage de référence.',
            ];
        }

        return $anomalies;
    }
}
