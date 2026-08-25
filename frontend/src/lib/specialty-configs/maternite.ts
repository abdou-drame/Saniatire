import type { SpecialtyFieldConfig } from "@/types/specialty-config";

/** Mirrors MaternityRecordRequest — estimated_delivery_date is deliberately absent, it's always server-computed. */
export const MATERNITY_RECORD_FIELDS: SpecialtyFieldConfig[] = [
  { key: "last_menstrual_period_date", label: "Date des dernières règles (DDR)", type: "date", required: true },
];

/** Mirrors MaternityPrenatalVisitRequest. */
export const MATERNITY_PRENATAL_VISIT_FIELDS: SpecialtyFieldConfig[] = [
  { key: "visit_number", label: "N° de consultation prénatale", type: "number", required: true, min: 1 },
  { key: "gestational_age_weeks", label: "Âge gestationnel", type: "number", required: true, min: 0, max: 45, unit: "SA" },
  { key: "visit_date", label: "Date de la visite", type: "date", required: true },
  { key: "weight_kg", label: "Poids", type: "number", min: 0, max: 300, step: "0.1", unit: "kg" },
  { key: "blood_pressure_systolic", label: "TA systolique", type: "number", min: 0, max: 300, unit: "mmHg" },
  { key: "blood_pressure_diastolic", label: "TA diastolique", type: "number", min: 0, max: 200, unit: "mmHg" },
  { key: "fundal_height_cm", label: "Hauteur utérine", type: "number", min: 0, max: 60, step: "0.1", unit: "cm" },
  { key: "fetal_heart_rate", label: "Bruits du cœur fœtal", type: "number", min: 0, max: 250, unit: "bpm" },
  { key: "fetal_movements", label: "Mouvements actifs fœtaux", type: "text" },
  { key: "notes", label: "Notes", type: "textarea" },
];

/** Mirrors MaternityPartogramRequest. */
export const MATERNITY_PARTOGRAM_FIELDS: SpecialtyFieldConfig[] = [
  { key: "labor_started_at", label: "Début du travail", type: "datetime", required: true },
];

/** Mirrors MaternityPartogramReadingRequest. */
export const MATERNITY_PARTOGRAM_READING_FIELDS: SpecialtyFieldConfig[] = [
  { key: "recorded_at", label: "Heure de la mesure", type: "datetime", required: true },
  { key: "cervical_dilation_cm", label: "Dilatation cervicale", type: "number", required: true, min: 0, max: 10, step: "0.5", unit: "cm" },
  { key: "fetal_heart_rate", label: "Bruits du cœur fœtal", type: "number", min: 0, max: 250, unit: "bpm" },
  { key: "contractions_per_10min", label: "Contractions / 10 min", type: "number", min: 0, max: 10 },
  { key: "notes", label: "Notes", type: "textarea" },
];

/** Mirrors MaternityDeliveryRequest. */
export const MATERNITY_DELIVERY_FIELDS: SpecialtyFieldConfig[] = [
  {
    key: "mode",
    label: "Mode d'accouchement",
    type: "select",
    required: true,
    options: [
      { value: "voie_basse", label: "Voie basse" },
      { value: "cesarienne", label: "Césarienne" },
    ],
  },
  { key: "delivered_at", label: "Date et heure d'accouchement", type: "datetime", required: true },
  { key: "complications", label: "Complications", type: "textarea" },
];

/**
 * Mirrors MaternityNewbornRequest. Apgar min/max: 0-10, enforced here for
 * immediate feedback — the server (MaternityNewbornRequest) enforces the
 * same range and remains authoritative.
 */
export const MATERNITY_NEWBORN_FIELDS: SpecialtyFieldConfig[] = [
  {
    key: "sex",
    label: "Sexe",
    type: "select",
    required: true,
    options: [
      { value: "m", label: "Masculin" },
      { value: "f", label: "Féminin" },
    ],
  },
  { key: "birth_weight_grams", label: "Poids de naissance", type: "number", required: true, min: 100, max: 7000, unit: "g" },
  { key: "apgar_1min", label: "Apgar 1 min", type: "number", required: true, min: 0, max: 10 },
  { key: "apgar_5min", label: "Apgar 5 min", type: "number", required: true, min: 0, max: 10 },
  { key: "apgar_10min", label: "Apgar 10 min", type: "number", min: 0, max: 10 },
];

/** Mirrors MaternityPostpartumVisitRequest. */
export const MATERNITY_POSTPARTUM_VISIT_FIELDS: SpecialtyFieldConfig[] = [
  { key: "visit_date", label: "Date de la visite", type: "date", required: true },
  { key: "blood_pressure_systolic", label: "TA systolique", type: "number", min: 0, max: 300, unit: "mmHg" },
  { key: "blood_pressure_diastolic", label: "TA diastolique", type: "number", min: 0, max: 200, unit: "mmHg" },
  { key: "temperature_c", label: "Température", type: "number", min: 25, max: 45, step: "0.1", unit: "°C" },
  {
    key: "bleeding_status",
    label: "Saignements",
    type: "select",
    options: [
      { value: "normal", label: "Normal" },
      { value: "anormal", label: "Anormal" },
    ],
  },
  { key: "breastfeeding_status", label: "Allaitement", type: "text" },
  { key: "notes", label: "Notes", type: "textarea" },
];
