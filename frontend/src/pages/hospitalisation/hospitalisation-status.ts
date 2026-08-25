import type { BadgeProps } from "@/components/ui/badge";
import type { BedStatus, HospitalizationStatus } from "@/types/api";

/** Shared display-only mappings for the Hospitalisation screens — labels/colors are purely
 * cosmetic and never used to decide whether an action is allowed (the backend does). */

export const BED_STATUS_LABEL: Record<BedStatus, string> = {
  libre: "Libre",
  occupe: "Occupé",
  reserve: "Réservé",
  entretien: "Entretien",
  indisponible: "Indisponible",
};

export const BED_STATUS_BADGE: Record<BedStatus, NonNullable<BadgeProps["status"]>> = {
  libre: "success",
  occupe: "danger",
  reserve: "warning",
  entretien: "neutral",
  indisponible: "neutral",
};

export const HOSPITALIZATION_STATUS_LABEL: Record<HospitalizationStatus, string> = {
  en_cours: "En cours",
  sorti: "Sorti",
  transfere: "Transféré",
};

export const HOSPITALIZATION_STATUS_BADGE: Record<HospitalizationStatus, NonNullable<BadgeProps["status"]>> = {
  en_cours: "accent",
  sorti: "success",
  transfere: "neutral",
};
