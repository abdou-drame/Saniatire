import type { SpecialtyFieldConfig } from "@/types/specialty-config";
import type { ToothStatus } from "@/types/specialty";

/** Mirrors DentalToothStateRequest — the 8 statuses accepted server-side. */
export const TOOTH_STATUS_OPTIONS: { value: ToothStatus; label: string }[] = [
  { value: "saine", label: "Saine" },
  { value: "cariee", label: "Cariée" },
  { value: "obturee", label: "Obturée" },
  { value: "extraite", label: "Extraite" },
  { value: "couronnee", label: "Couronnée" },
  { value: "absente", label: "Absente" },
  { value: "implant", label: "Implant" },
  { value: "bridge", label: "Bridge" },
];

/** Tailwind classes per tooth status — the single source of truth for the odontogram's color code. */
export const TOOTH_STATUS_STYLES: Record<ToothStatus, string> = {
  saine: "bg-surface border-border text-text-subtle",
  cariee: "bg-danger/15 border-danger/40 text-danger",
  obturee: "bg-accent/15 border-accent/40 text-accent-light",
  couronnee: "bg-accent2/15 border-accent2/40 text-accent2-light",
  extraite: "bg-surface-hover border-border-strong text-text-subtle line-through opacity-60",
  absente: "border-dashed bg-transparent border-border text-text-subtle opacity-50",
  implant: "bg-warning/15 border-warning/40 text-warning",
  bridge: "bg-success/15 border-success/40 text-success",
};

export const TOOTH_STATE_FIELDS: SpecialtyFieldConfig[] = [
  {
    key: "status",
    label: "État de la dent",
    type: "select",
    required: true,
    options: TOOTH_STATUS_OPTIONS,
  },
  { key: "notes", label: "Notes", type: "textarea" },
];

/** The 32 permanent-dentition FDI codes — the simplified scope the odontogram covers (deciduous teeth excluded). */
export const PERMANENT_TEETH_FDI: string[] = [1, 2, 3, 4].flatMap((quadrant) =>
  Array.from({ length: 8 }, (_, i) => `${quadrant}${i + 1}`),
);

const TOOTH_FDI_OPTIONS = PERMANENT_TEETH_FDI.map((fdi) => ({ value: fdi, label: fdi }));

/** Mirrors DentalProcedureRequest. */
export const DENTAL_PROCEDURE_FIELDS: SpecialtyFieldConfig[] = [
  { key: "tooth_fdi", label: "Dent (FDI)", type: "select", options: TOOTH_FDI_OPTIONS },
  { key: "act_type", label: "Type d'acte", type: "text", required: true },
  { key: "performed_at", label: "Date de l'acte", type: "date", required: true },
  { key: "notes", label: "Notes", type: "textarea" },
];

/** Mirrors DentalTreatmentPlanItemRequest (create). status defaults server-side to "prevu". */
export const DENTAL_TREATMENT_PLAN_ITEM_FIELDS: SpecialtyFieldConfig[] = [
  { key: "tooth_fdi", label: "Dent (FDI)", type: "select", options: TOOTH_FDI_OPTIONS },
  { key: "act_type", label: "Acte prévu", type: "text", required: true },
  { key: "planned_at", label: "Date prévue", type: "date" },
];
