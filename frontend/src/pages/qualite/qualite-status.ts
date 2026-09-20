import type { BadgeProps } from "@/components/ui/badge";
import type { ComplaintOrigin, ComplaintStatut } from "@/types/api";

/** Mappings d'affichage uniquement pour l'écran Qualité/Réclamations — labels/couleurs
 * sont purement cosmétiques et ne servent jamais à décider si une action est autorisée
 * (le backend, via les abort_if() de ComplaintController, reste seul arbitre). */

export const COMPLAINT_STATUT_LABEL: Record<ComplaintStatut, string> = {
  ouverte: "Ouverte",
  en_cours: "En cours",
  resolue: "Résolue",
  close: "Clôturée",
};

export const COMPLAINT_STATUT_BADGE: Record<ComplaintStatut, NonNullable<BadgeProps["status"]>> = {
  ouverte: "warning",
  en_cours: "accent",
  resolue: "success",
  close: "neutral",
};

export const COMPLAINT_ORIGIN_LABEL: Record<ComplaintOrigin, string> = {
  staff: "Saisie personnel",
  patient: "Portail patient",
};

export const COMPLAINT_ORIGIN_BADGE: Record<ComplaintOrigin, NonNullable<BadgeProps["status"]>> = {
  staff: "neutral",
  patient: "accent",
};
