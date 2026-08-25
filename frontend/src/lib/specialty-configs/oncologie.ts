import type { SpecialtyFieldConfig } from "@/types/specialty-config";

const STAGE_T_OPTIONS = ["Tis", "T0", "T1", "T2", "T3", "T4"].map((value) => ({ value, label: value }));
const STAGE_N_OPTIONS = ["N0", "N1", "N2", "N3"].map((value) => ({ value, label: value }));
const STAGE_M_OPTIONS = ["M0", "M1"].map((value) => ({ value, label: value }));

/** Mirrors OncoRecordRequest (create). */
export const ONCO_RECORD_FIELDS: SpecialtyFieldConfig[] = [
  { key: "cancer_type", label: "Type de cancer", type: "text", required: true },
  { key: "stage_t", label: "Stade T", type: "select", options: STAGE_T_OPTIONS },
  { key: "stage_n", label: "Stade N", type: "select", options: STAGE_N_OPTIONS },
  { key: "stage_m", label: "Stade M", type: "select", options: STAGE_M_OPTIONS },
  { key: "protocol_name", label: "Protocole", type: "text" },
  { key: "treatment_line", label: "Ligne de traitement", type: "number", min: 1, max: 20 },
  { key: "diagnosed_at", label: "Date du diagnostic", type: "date" },
];

/** Mirrors OncoChemoCycleRequest. */
export const ONCO_CHEMO_CYCLE_FIELDS: SpecialtyFieldConfig[] = [
  { key: "cycle_number", label: "Numéro du cycle", type: "number", required: true, min: 1, max: 200 },
  { key: "cycle_date", label: "Date du cycle", type: "date", required: true },
  { key: "medications", label: "Médicaments", type: "textarea" },
  { key: "side_effects", label: "Effets secondaires", type: "textarea" },
];

/** Mirrors OncoResponseEvaluationRequest. */
export const ONCO_RESPONSE_EVALUATION_FIELDS: SpecialtyFieldConfig[] = [
  { key: "evaluated_at", label: "Date de l'évaluation", type: "date", required: true },
  {
    key: "response",
    label: "Réponse au traitement",
    type: "select",
    required: true,
    options: [
      { value: "reponse_complete", label: "Réponse complète" },
      { value: "reponse_partielle", label: "Réponse partielle" },
      { value: "stable", label: "Stable" },
      { value: "progression", label: "Progression" },
    ],
  },
  { key: "notes", label: "Notes", type: "textarea" },
];
