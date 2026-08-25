import type { SpecialtyFieldConfig } from "@/types/specialty-config";

/** Mirrors CardioRecordRequest (create). */
export const CARDIO_RECORD_FIELDS: SpecialtyFieldConfig[] = [
  {
    key: "risk_factors",
    label: "Facteurs de risque",
    type: "checkboxes",
    options: [
      { value: "diabete", label: "Diabète" },
      { value: "hta", label: "HTA" },
      { value: "tabac", label: "Tabac" },
      { value: "obesite", label: "Obésité" },
      { value: "dyslipidemie", label: "Dyslipidémie" },
      { value: "sedentarite", label: "Sédentarité" },
      { value: "antecedents_familiaux", label: "Antécédents familiaux" },
    ],
  },
  { key: "current_treatment", label: "Traitement en cours", type: "textarea" },
  { key: "examined_at", label: "Date de l'examen", type: "date", required: true },
];

/** Mirrors CardioReadingRequest — a repeatable blood-pressure/heart-rate measurement. */
export const CARDIO_READING_FIELDS: SpecialtyFieldConfig[] = [
  { key: "measured_at", label: "Heure de la mesure", type: "datetime", required: true },
  { key: "blood_pressure_systolic", label: "TA systolique", type: "number", required: true, min: 0, max: 300, unit: "mmHg" },
  { key: "blood_pressure_diastolic", label: "TA diastolique", type: "number", required: true, min: 0, max: 200, unit: "mmHg" },
  { key: "heart_rate", label: "Fréquence cardiaque", type: "number", required: true, min: 20, max: 300, unit: "bpm" },
  {
    key: "rhythm",
    label: "Rythme",
    type: "select",
    options: [
      { value: "regulier", label: "Régulier" },
      { value: "irregulier", label: "Irrégulier" },
    ],
  },
];

/** Mirrors CardioEcgResultRequest. */
export const CARDIO_ECG_RESULT_FIELDS: SpecialtyFieldConfig[] = [
  { key: "performed_at", label: "Date de l'ECG", type: "datetime", required: true },
  { key: "rhythm", label: "Rythme observé", type: "text", required: true },
  { key: "heart_rate", label: "Fréquence cardiaque", type: "number", min: 20, max: 300, unit: "bpm" },
  { key: "anomalies", label: "Anomalies", type: "textarea" },
  { key: "tracing_file_reference", label: "Référence du tracé", type: "text" },
];
