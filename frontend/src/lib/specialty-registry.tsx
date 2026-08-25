import {
  Baby,
  Brain,
  Droplets,
  Dumbbell,
  Eye,
  HardHat,
  HeartPulse,
  Home,
  Radiation,
  Ruler,
  Smile,
  TestTube,
  type LucideIcon,
} from "lucide-react";
import type { SpecialtyType } from "@/types/specialty";

/**
 * Frontend mirror of the backend's `SpecialtyRegistry` (specialty_type =>
 * [Model, Resource]): one place mapping a specialty's key to how it's
 * presented (label, icon, accent color, entry route, and the permission
 * that gates whether its entry point is even shown) across the app — the
 * patient record's entry buttons, and the patient timeline's per-specialty
 * icon. Adding a specialty means adding one entry here, nothing else
 * changes.
 */
export interface SpecialtyUiMeta {
  type: SpecialtyType;
  label: string;
  icon: LucideIcon;
  /** Tailwind color tokens already defined in the design system (index.css), same convention as Badge's `status` variants. */
  colorClass: string;
  route: (patientId: number) => string;
  /** Mirrors the backend's `<module>.view` permission — the entry point (menu/button) is hidden, not just gated, when the user lacks it. */
  viewPermission: string;
}

export const SPECIALTY_UI_REGISTRY: Record<SpecialtyType, SpecialtyUiMeta> = {
  maternite: {
    type: "maternite",
    label: "Maternité",
    icon: Baby,
    colorClass: "bg-accent2/15 text-accent2-light",
    route: (patientId) => `/patients/${patientId}/maternite`,
    viewPermission: "maternite.view",
  },
  dentaire: {
    type: "dentaire",
    label: "Dentaire",
    icon: Smile,
    colorClass: "bg-accent/15 text-accent-light",
    route: (patientId) => `/patients/${patientId}/dentaire`,
    viewPermission: "dentaire.view",
  },
  dialyse: {
    type: "dialyse",
    label: "Dialyse",
    icon: Droplets,
    colorClass: "bg-warning/15 text-warning",
    route: (patientId) => `/patients/${patientId}/dialyse`,
    viewPermission: "dialyse.view",
  },
  ophtalmo: {
    type: "ophtalmo",
    label: "Ophtalmologie",
    icon: Eye,
    colorClass: "bg-accent/15 text-accent-light",
    route: (patientId) => `/patients/${patientId}/ophtalmo`,
    viewPermission: "ophtalmo.view",
  },
  cardiologie: {
    type: "cardiologie",
    label: "Cardiologie",
    icon: HeartPulse,
    colorClass: "bg-danger/15 text-danger",
    route: (patientId) => `/patients/${patientId}/cardiologie`,
    viewPermission: "cardiologie.view",
  },
  kinesitherapie: {
    type: "kinesitherapie",
    label: "Kinésithérapie",
    icon: Dumbbell,
    colorClass: "bg-success/15 text-success",
    route: (patientId) => `/patients/${patientId}/kinesitherapie`,
    viewPermission: "kinesitherapie.view",
  },
  oncologie: {
    type: "oncologie",
    label: "Oncologie",
    icon: Radiation,
    colorClass: "bg-accent2/15 text-accent2-light",
    route: (patientId) => `/patients/${patientId}/oncologie`,
    viewPermission: "oncologie.view",
  },
  pma: {
    type: "pma",
    label: "PMA / Fertilité",
    icon: TestTube,
    colorClass: "bg-accent/15 text-accent-light",
    route: (patientId) => `/patients/${patientId}/pma`,
    viewPermission: "pma.view",
  },
  sante_mentale: {
    type: "sante_mentale",
    label: "Santé mentale",
    icon: Brain,
    colorClass: "bg-accent2/15 text-accent2-light",
    route: (patientId) => `/patients/${patientId}/sante-mentale`,
    viewPermission: "sante_mentale.view",
  },
  pediatrie: {
    type: "pediatrie",
    label: "Pédiatrie",
    icon: Ruler,
    colorClass: "bg-warning/15 text-warning",
    route: (patientId) => `/patients/${patientId}/pediatrie`,
    viewPermission: "pediatrie.view",
  },
  medecine_travail: {
    type: "medecine_travail",
    label: "Médecine du travail",
    icon: HardHat,
    colorClass: "bg-success/15 text-success",
    route: (patientId) => `/patients/${patientId}/medecine-travail`,
    viewPermission: "medecine_travail.view",
  },
  soins_domicile: {
    type: "soins_domicile",
    label: "Soins à domicile",
    icon: Home,
    colorClass: "bg-danger/15 text-danger",
    route: (patientId) => `/patients/${patientId}/soins-domicile`,
    viewPermission: "soins_domicile.view",
  },
};

export function specialtyUiMeta(specialtyType: string | null | undefined): SpecialtyUiMeta | null {
  if (!specialtyType) return null;
  return SPECIALTY_UI_REGISTRY[specialtyType as SpecialtyType] ?? null;
}
