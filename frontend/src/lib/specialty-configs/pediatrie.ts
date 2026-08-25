import type { SpecialtyFieldConfig } from "@/types/specialty-config";

/**
 * Mirrors PediatricRecordRequest (create) — the backend request has no
 * clinical fields at all (just site_id/patient_id/consultation_id, set
 * automatically), so the root record is a thin container with an empty
 * field list. The generic architecture holds even for this degenerate
 * case: SpecialtyForm/useSpecialtyAddForm render/validate fine with [].
 */
export const PEDIATRIC_RECORD_FIELDS: SpecialtyFieldConfig[] = [];

/** Mirrors PediatricGrowthMeasurementRequest. */
export const PEDIATRIC_GROWTH_MEASUREMENT_FIELDS: SpecialtyFieldConfig[] = [
  { key: "measured_at", label: "Date de la mesure", type: "date", required: true },
  { key: "weight_kg", label: "Poids", type: "number", min: 0, max: 200, unit: "kg", step: "0.01" },
  { key: "height_cm", label: "Taille", type: "number", min: 0, max: 250, unit: "cm", step: "0.1" },
  { key: "head_circumference_cm", label: "Périmètre crânien", type: "number", min: 0, max: 100, unit: "cm", step: "0.1" },
];

/** Mirrors PediatricVaccinationRequest. */
export const PEDIATRIC_VACCINATION_FIELDS: SpecialtyFieldConfig[] = [
  { key: "vaccine_name", label: "Vaccin", type: "text", required: true },
  { key: "dose_number", label: "Numéro de dose", type: "number", min: 1, max: 20 },
  { key: "administered_at", label: "Date d'administration", type: "date", required: true },
];

/** Mirrors PediatricDevelopmentObservationRequest. */
export const PEDIATRIC_DEVELOPMENT_OBSERVATION_FIELDS: SpecialtyFieldConfig[] = [
  { key: "age_months", label: "Âge (mois)", type: "number", required: true, min: 0, max: 216 },
  { key: "observation", label: "Observation", type: "textarea", required: true },
  { key: "observed_at", label: "Date de l'observation", type: "date", required: true },
];
