import type { SpecialtyFieldConfig } from "@/types/specialty-config";

/** Mirrors KineProgramRequest (create). */
export const KINE_PROGRAM_FIELDS: SpecialtyFieldConfig[] = [
  { key: "affected_area", label: "Zone traitée", type: "text", required: true },
  { key: "initial_range_of_motion", label: "Amplitude initiale", type: "text" },
  { key: "initial_pain_scale", label: "Douleur initiale (EVA)", type: "number", min: 0, max: 10 },
  { key: "objectives", label: "Objectifs", type: "textarea" },
  { key: "started_at", label: "Date de début", type: "date", required: true },
];

/** Mirrors KineSessionRequest — practitioner_id is set server-side. */
export const KINE_SESSION_FIELDS: SpecialtyFieldConfig[] = [
  { key: "session_date", label: "Date de la séance", type: "datetime", required: true },
  { key: "exercises_performed", label: "Exercices réalisés", type: "textarea" },
  { key: "evolution", label: "Évolution", type: "textarea" },
  { key: "pain_scale", label: "Douleur (EVA)", type: "number", min: 0, max: 10 },
  { key: "observations", label: "Observations", type: "textarea" },
];
