import type { BadgeProps } from "@/components/ui/badge";
import type { TeleconsultationStatut } from "@/types/api";

/** Mappings d'affichage uniquement pour l'écran Téléconsultation — labels/couleurs
 * sont purement cosmétiques et ne servent jamais à décider si une action est autorisée
 * (le backend, via les abort_if() de TeleconsultationController, reste seul arbitre). */

export const TELECONSULTATION_STATUT_LABEL: Record<TeleconsultationStatut, string> = {
  planifiee: "Planifiée",
  en_cours: "En cours",
  terminee: "Terminée",
  annulee: "Annulée",
};

export const TELECONSULTATION_STATUT_BADGE: Record<TeleconsultationStatut, NonNullable<BadgeProps["status"]>> = {
  planifiee: "warning",
  en_cours: "accent",
  terminee: "success",
  annulee: "neutral",
};
