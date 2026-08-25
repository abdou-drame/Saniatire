import type { SpecialtyFieldConfig } from "@/types/specialty-config";

/** Mirrors MentalHealthRecordRequest (create). */
export const MENTAL_HEALTH_RECORD_FIELDS: SpecialtyFieldConfig[] = [
  { key: "consultation_reason", label: "Motif de consultation", type: "text", required: true },
  { key: "clinical_evaluation", label: "Évaluation clinique", type: "textarea" },
  { key: "ongoing_treatment", label: "Suivi thérapeutique", type: "textarea" },
];

/**
 * Mirrors MentalHealthScaleScoreRequest. The backend deliberately keeps a
 * generic 0-999.99 bound rather than a per-scale range (PHQ-9, HAD, MADRS...
 * each differ) — scale_name is free text, not a fixed select.
 */
export const MENTAL_HEALTH_SCALE_SCORE_FIELDS: SpecialtyFieldConfig[] = [
  { key: "scale_name", label: "Échelle utilisée", type: "text", required: true, placeholder: "ex. PHQ-9, HAD, MADRS" },
  { key: "score", label: "Score", type: "number", required: true, min: 0, max: 999.99, step: "0.01" },
  { key: "scored_at", label: "Date de la mesure", type: "date", required: true },
];
