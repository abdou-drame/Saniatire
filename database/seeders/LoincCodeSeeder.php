<?php

namespace Database\Seeders;

use App\Domain\Laboratoire\Models\LoincCode;
use Illuminate\Database\Seeder;

/**
 * Restricted but real dataset (~30 common analyses) with genuine LOINC
 * codes, covering hematology, biochemistry, serology and microbiology —
 * enough for the lab module to work end-to-end without importing the full
 * official LOINC table. See README-LOINC.md for how to import it later.
 * Codes should be cross-checked against the official LOINC table
 * (loinc.org) before any production use.
 */
class LoincCodeSeeder extends Seeder
{
    private const CODES = [
        // Hématologie
        ['58410-2', 'Numération formule sanguine (NFS) complète', 'Hématologie', null],
        ['6690-2', 'Leucocytes (globules blancs)', 'Hématologie', '10*3/uL'],
        ['789-8', 'Érythrocytes (globules rouges)', 'Hématologie', '10*6/uL'],
        ['718-7', 'Hémoglobine', 'Hématologie', 'g/dL'],
        ['4544-3', 'Hématocrite', 'Hématologie', '%'],
        ['777-3', 'Plaquettes', 'Hématologie', '10*3/uL'],
        ['6301-6', 'INR (taux de prothrombine)', 'Hématologie', null],
        ['5902-2', 'Temps de prothrombine (TP)', 'Hématologie', 's'],
        ['48065-7', 'D-dimères', 'Hématologie', 'ng/mL'],

        // Biochimie
        ['2345-7', 'Glycémie', 'Biochimie', 'g/L'],
        ['4548-4', 'Hémoglobine glyquée (HbA1c)', 'Biochimie', '%'],
        ['2160-0', 'Créatinine', 'Biochimie', 'mg/L'],
        ['3094-0', 'Urée', 'Biochimie', 'g/L'],
        ['1742-6', 'ALAT (transaminases)', 'Biochimie', 'UI/L'],
        ['1920-8', 'ASAT (transaminases)', 'Biochimie', 'UI/L'],
        ['2093-3', 'Cholestérol total', 'Biochimie', 'g/L'],
        ['2085-9', 'Cholestérol HDL', 'Biochimie', 'g/L'],
        ['2089-1', 'Cholestérol LDL', 'Biochimie', 'g/L'],
        ['2571-8', 'Triglycérides', 'Biochimie', 'g/L'],
        ['2951-2', 'Sodium', 'Biochimie', 'mmol/L'],
        ['2823-3', 'Potassium', 'Biochimie', 'mmol/L'],
        ['2075-0', 'Chlore', 'Biochimie', 'mmol/L'],
        ['17861-6', 'Calcium', 'Biochimie', 'mg/L'],
        ['2276-4', 'Ferritine', 'Biochimie', 'ng/mL'],
        ['1989-3', 'Vitamine D (25-OH)', 'Biochimie', 'ng/mL'],

        // Immuno-hématologie / Sérologie
        ['883-9', 'Groupe sanguin ABO', 'Immuno-hématologie', null],
        ['10331-7', 'Groupe sanguin Rhésus (D)', 'Immuno-hématologie', null],
        ['1988-5', 'Protéine C réactive (CRP)', 'Sérologie', 'mg/L'],
        ['3016-3', 'TSH', 'Hormonologie', 'mUI/L'],
        ['2857-1', 'PSA total', 'Hormonologie', 'ng/mL'],
        ['20416-4', 'Beta-hCG', 'Hormonologie', 'mUI/mL'],

        // Microbiologie
        ['600-7', 'Examen cytobactériologique des urines (ECBU) — culture', 'Microbiologie', null],
    ];

    public function run(): void
    {
        foreach (self::CODES as [$code, $label, $component, $unit]) {
            LoincCode::query()->firstOrCreate(
                ['code' => $code],
                ['label' => $label, 'component' => $component, 'default_unit' => $unit, 'version' => 'LOINC', 'status' => 'actif']
            );
        }
    }
}
