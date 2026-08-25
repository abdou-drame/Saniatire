import type { SpecialtyFieldConfig } from "@/types/specialty-config";

/** Mirrors OccupationalHealthRecordRequest. No child sub-resources — each visit is a standalone record. */
export const OCCUPATIONAL_HEALTH_RECORD_FIELDS: SpecialtyFieldConfig[] = [
  {
    key: "visit_type",
    label: "Type de visite",
    type: "select",
    required: true,
    options: [
      { value: "embauche", label: "Embauche" },
      { value: "periodique", label: "Périodique" },
      { value: "reprise", label: "Reprise" },
      { value: "demande", label: "Demande" },
    ],
  },
  {
    key: "fitness_status",
    label: "Aptitude au poste",
    type: "select",
    required: true,
    options: [
      { value: "apte", label: "Apte" },
      { value: "apte_avec_reserves", label: "Apte avec réserves" },
      { value: "inapte", label: "Inapte" },
    ],
  },
  {
    key: "restrictions",
    label: "Restrictions",
    type: "textarea",
    help: "Obligatoire si l'aptitude est « Apte avec réserves ».",
  },
  {
    key: "risk_exposures",
    label: "Expositions aux risques",
    type: "checkboxes",
    options: [
      { value: "bruit", label: "Bruit" },
      { value: "produits_chimiques", label: "Produits chimiques" },
      { value: "poussieres", label: "Poussières" },
      { value: "port_de_charges", label: "Port de charges" },
      { value: "travail_en_hauteur", label: "Travail en hauteur" },
      { value: "ecran", label: "Travail sur écran" },
      { value: "rayonnements", label: "Rayonnements" },
      { value: "biologique", label: "Risque biologique" },
    ],
  },
  { key: "visit_date", label: "Date de la visite", type: "date", required: true },
  { key: "next_visit_due_at", label: "Prochaine visite obligatoire", type: "date" },
];
