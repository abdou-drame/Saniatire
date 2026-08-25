import type { SpecialtyFieldConfig } from "@/types/specialty-config";

/** Mirrors HomeCareRecordRequest (create). */
export const HOME_CARE_RECORD_FIELDS: SpecialtyFieldConfig[] = [
  { key: "intervention_address", label: "Adresse d'intervention", type: "text", required: true },
  { key: "care_type", label: "Type de soins", type: "text", required: true },
];

/** Mirrors HomeCareVisitRequest — intervenant_id is set server-side. */
export const HOME_CARE_VISIT_FIELDS: SpecialtyFieldConfig[] = [
  { key: "care_type", label: "Type de soins", type: "text", required: true },
  { key: "visit_datetime", label: "Date et heure du passage", type: "datetime", required: true },
  { key: "report", label: "Compte rendu", type: "textarea" },
];
