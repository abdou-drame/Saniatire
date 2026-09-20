import type { BadgeProps } from "@/components/ui/badge";
import type { StatutEmploi } from "@/types/api";

/** Mappings d'affichage uniquement pour l'écran Personnel — labels/couleurs sont purement
 * cosmétiques et ne servent jamais à décider si une action est autorisée (le backend décide). */

export const STATUT_EMPLOI_LABEL: Record<StatutEmploi, string> = {
  actif: "Actif",
  en_conge: "En congé",
  suspendu: "Suspendu",
  termine: "Terminé",
};

export const STATUT_EMPLOI_BADGE: Record<StatutEmploi, NonNullable<BadgeProps["status"]>> = {
  actif: "success",
  en_conge: "warning",
  suspendu: "danger",
  termine: "neutral",
};
