import type { SpecialtyFieldConfig } from "@/types/specialty-config";

/** Mirrors DialysisProgramRequest (create). */
export const DIALYSIS_PROGRAM_FIELDS: SpecialtyFieldConfig[] = [
  { key: "frequency_per_week", label: "Fréquence", type: "number", required: true, min: 1, max: 7, unit: "séances/sem." },
  { key: "dry_weight_kg", label: "Poids sec cible", type: "number", required: true, min: 0, max: 300, step: "0.1", unit: "kg" },
  {
    key: "vascular_access_type",
    label: "Abord vasculaire",
    type: "select",
    required: true,
    options: [
      { value: "fistule", label: "Fistule" },
      { value: "catheter", label: "Cathéter" },
      { value: "greffon", label: "Greffon" },
    ],
  },
  {
    key: "vascular_access_status",
    label: "État de l'abord",
    type: "select",
    options: [
      { value: "fonctionnel", label: "Fonctionnel" },
      { value: "complique", label: "Compliqué" },
    ],
  },
  { key: "started_at", label: "Date de début du programme", type: "date", required: true },
];

/** Mirrors DialysisSessionRequest. */
export const DIALYSIS_SESSION_FIELDS: SpecialtyFieldConfig[] = [
  { key: "session_date", label: "Date de la séance", type: "datetime", required: true },
  { key: "pre_weight_kg", label: "Poids pré-dialyse", type: "number", required: true, min: 0, max: 300, step: "0.1", unit: "kg" },
  { key: "post_weight_kg", label: "Poids post-dialyse", type: "number", min: 0, max: 300, step: "0.1", unit: "kg" },
  { key: "dry_weight_kg", label: "Poids sec", type: "number", min: 0, max: 300, step: "0.1", unit: "kg" },
  { key: "duration_minutes", label: "Durée", type: "number", min: 0, max: 600, unit: "min" },
  { key: "blood_flow_rate_ml_min", label: "Débit sanguin", type: "number", min: 0, max: 1000, unit: "mL/min" },
  { key: "ultrafiltration_volume_ml", label: "Volume d'ultrafiltration", type: "number", min: 0, max: 10000, unit: "mL" },
  { key: "complications", label: "Complications", type: "textarea" },
];

/** Mirrors DialysisSessionVitalRequest. */
export const DIALYSIS_SESSION_VITAL_FIELDS: SpecialtyFieldConfig[] = [
  { key: "measured_at", label: "Heure de la mesure", type: "datetime", required: true },
  { key: "blood_pressure_systolic", label: "TA systolique", type: "number", required: true, min: 0, max: 300, unit: "mmHg" },
  { key: "blood_pressure_diastolic", label: "TA diastolique", type: "number", required: true, min: 0, max: 200, unit: "mmHg" },
  { key: "heart_rate", label: "Fréquence cardiaque", type: "number", required: true, min: 0, max: 250, unit: "bpm" },
];
