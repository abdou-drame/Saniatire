import type { BadgeProps } from "@/components/ui/badge";
import type { LabOrderStatus, LabResultStatus } from "@/types/api";

/** Shared display-only mappings for the Laboratoire screens — labels/colors are purely
 * cosmetic and never used to decide whether an action is allowed (the backend does). */

export const ORDER_STATUS_LABEL: Record<LabOrderStatus, string> = {
  demande: "Demande",
  prelevement_effectue: "Prélèvement effectué",
  en_analyse: "En analyse",
  resultats_disponibles: "Résultats disponibles",
  transmis: "Transmis",
  annule: "Annulé",
};

export const ORDER_STATUS_BADGE: Record<LabOrderStatus, NonNullable<BadgeProps["status"]>> = {
  demande: "neutral",
  prelevement_effectue: "accent",
  en_analyse: "accent2",
  resultats_disponibles: "warning",
  transmis: "success",
  annule: "danger",
};

export const RESULT_STATUS_LABEL: Record<LabResultStatus, string> = {
  validation_technique_attente: "Attente validation technique",
  validation_biologique_attente: "Attente validation biologique",
  valide: "Validé",
  transmis: "Transmis",
};

export const RESULT_STATUS_BADGE: Record<LabResultStatus, NonNullable<BadgeProps["status"]>> = {
  validation_technique_attente: "warning",
  validation_biologique_attente: "warning",
  valide: "accent",
  transmis: "success",
};
