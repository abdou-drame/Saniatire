import type { SpecialtyFieldConfig } from "@/types/specialty-config";

/**
 * Mirrors OphtalmoRecordRequest. No child sub-resources — each exam is a
 * standalone, self-contained record (unlike maternité/dialyse/dentaire's
 * root+children shape), so the screen lists these directly instead of
 * gating behind a "create the root record" step.
 */
export const OPHTALMO_RECORD_FIELDS: SpecialtyFieldConfig[] = [
  { key: "visual_acuity_od_uncorrected", label: "AV œil droit (sans correction)", type: "text", placeholder: "ex. 5/10" },
  { key: "visual_acuity_od_corrected", label: "AV œil droit (avec correction)", type: "text", placeholder: "ex. 10/10" },
  { key: "visual_acuity_og_uncorrected", label: "AV œil gauche (sans correction)", type: "text", placeholder: "ex. 5/10" },
  { key: "visual_acuity_og_corrected", label: "AV œil gauche (avec correction)", type: "text", placeholder: "ex. 10/10" },
  { key: "intraocular_pressure_od", label: "Tension oculaire OD", type: "number", min: 0, max: 80, unit: "mmHg" },
  { key: "intraocular_pressure_og", label: "Tension oculaire OG", type: "number", min: 0, max: 80, unit: "mmHg" },
  { key: "refraction_od_sphere", label: "Réfraction OD — sphère", type: "number", min: -30, max: 30, step: "0.25", unit: "D" },
  { key: "refraction_od_cylinder", label: "Réfraction OD — cylindre", type: "number", min: -10, max: 10, step: "0.25", unit: "D" },
  { key: "refraction_od_axis", label: "Réfraction OD — axe", type: "number", min: 0, max: 180, unit: "°" },
  { key: "refraction_og_sphere", label: "Réfraction OG — sphère", type: "number", min: -30, max: 30, step: "0.25", unit: "D" },
  { key: "refraction_og_cylinder", label: "Réfraction OG — cylindre", type: "number", min: -10, max: 10, step: "0.25", unit: "D" },
  { key: "refraction_og_axis", label: "Réfraction OG — axe", type: "number", min: 0, max: 180, unit: "°" },
  { key: "fundus_exam", label: "Observations fond d'œil", type: "textarea" },
  { key: "optical_correction_prescription", label: "Prescription de correction optique", type: "textarea" },
  { key: "examined_at", label: "Date de l'examen", type: "date", required: true },
];
