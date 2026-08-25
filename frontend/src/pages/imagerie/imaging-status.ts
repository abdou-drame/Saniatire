import type { BadgeProps } from "@/components/ui/badge";
import type { ImagingExamType, ImagingOrderStatus, ImagingReportStatus, ImagingStudyStatus } from "@/types/api";

/** Shared display-only mappings for the Imagerie screens — labels/colors are purely
 * cosmetic and never used to decide whether an action is allowed (the backend does). */

export const ORDER_STATUS_LABEL: Record<ImagingOrderStatus, string> = {
  demande: "Demande",
  planifie: "Planifié",
  realise: "Réalisé",
  en_interpretation: "En interprétation",
  cr_redige: "Compte rendu rédigé",
  valide: "Validé",
  transmis: "Transmis",
  annule: "Annulé",
};

export const ORDER_STATUS_BADGE: Record<ImagingOrderStatus, NonNullable<BadgeProps["status"]>> = {
  demande: "neutral",
  planifie: "accent",
  realise: "accent2",
  en_interpretation: "accent2",
  cr_redige: "warning",
  valide: "warning",
  transmis: "success",
  annule: "danger",
};

export const STUDY_STATUS_LABEL: Record<ImagingStudyStatus, string> = {
  realise: "Réalisé",
  en_interpretation: "En interprétation",
  cr_redige: "Compte rendu rédigé",
  valide: "Validé",
  transmis: "Transmis",
};

export const STUDY_STATUS_BADGE: Record<ImagingStudyStatus, NonNullable<BadgeProps["status"]>> = {
  realise: "accent2",
  en_interpretation: "accent2",
  cr_redige: "warning",
  valide: "accent",
  transmis: "success",
};

export const REPORT_STATUS_LABEL: Record<ImagingReportStatus, string> = {
  brouillon: "Brouillon",
  valide: "Validé",
};

export const REPORT_STATUS_BADGE: Record<ImagingReportStatus, NonNullable<BadgeProps["status"]>> = {
  brouillon: "warning",
  valide: "success",
};

export const EXAM_TYPE_LABEL: Record<ImagingExamType, string> = {
  radio: "Radiographie",
  echo: "Échographie",
  scanner: "Scanner",
  irm: "IRM",
};
