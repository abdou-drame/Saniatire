import type { SpecialtyFieldConfig } from "@/types/specialty-config";

/** Mirrors PmaRecordRequest (create). attempt_result defaults server-side if omitted. */
export const PMA_RECORD_FIELDS: SpecialtyFieldConfig[] = [
  { key: "fertility_history", label: "Bilan de fertilité", type: "textarea" },
  { key: "exams_performed", label: "Examens réalisés", type: "textarea" },
  {
    key: "attempt_result",
    label: "Résultat de la tentative",
    type: "select",
    options: [
      { value: "en_cours", label: "En cours" },
      { value: "positif", label: "Positif" },
      { value: "negatif", label: "Négatif" },
    ],
  },
];

/** Mirrors PmaStimulationProtocolRequest. */
export const PMA_STIMULATION_PROTOCOL_FIELDS: SpecialtyFieldConfig[] = [
  { key: "protocol_type", label: "Type de protocole", type: "text", required: true },
  { key: "medications", label: "Médicaments", type: "textarea" },
  { key: "started_at", label: "Date de début", type: "date", required: true },
  { key: "ended_at", label: "Date de fin", type: "date" },
];

/** Mirrors PmaCycleMonitoringRequest. */
export const PMA_CYCLE_MONITORING_FIELDS: SpecialtyFieldConfig[] = [
  { key: "monitoring_date", label: "Date du suivi", type: "date", required: true },
  { key: "echo_observations", label: "Observations échographiques", type: "textarea" },
  { key: "hormone_level", label: "Taux hormonal", type: "number", min: 0, max: 999999, step: "0.01" },
  { key: "puncture_date", label: "Date de ponction", type: "date" },
  { key: "transfer_date", label: "Date de transfert", type: "date" },
];
