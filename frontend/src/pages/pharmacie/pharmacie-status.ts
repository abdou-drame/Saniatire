import type { BadgeProps } from "@/components/ui/badge";
import type { ProductCategorie, StockMovementType } from "@/types/api";

/** Mappings d'affichage uniquement pour les écrans Pharmacie/Stocks — labels/couleurs sont
 * purement cosmétiques et ne servent jamais à décider si une action est autorisée (le backend décide). */

export const CATEGORIE_LABEL: Record<ProductCategorie, string> = {
  medicament: "Médicament",
  consommable: "Consommable",
  dispositif_medical: "Dispositif médical",
};

export const CATEGORIE_BADGE: Record<ProductCategorie, NonNullable<BadgeProps["status"]>> = {
  medicament: "accent",
  consommable: "accent2",
  dispositif_medical: "neutral",
};

export const MOVEMENT_TYPE_LABEL: Record<StockMovementType, string> = {
  entree: "Entrée",
  sortie: "Sortie",
  ajustement: "Ajustement",
  transfert: "Transfert",
};

export const MOVEMENT_TYPE_BADGE: Record<StockMovementType, NonNullable<BadgeProps["status"]>> = {
  entree: "success",
  sortie: "warning",
  ajustement: "neutral",
  transfert: "accent",
};
