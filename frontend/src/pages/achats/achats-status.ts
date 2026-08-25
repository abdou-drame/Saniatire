import type { BadgeProps } from "@/components/ui/badge";
import type { PurchaseOrderStatut, PurchaseRequestStatut } from "@/types/api";

/** Mappings d'affichage uniquement pour les écrans Achats/Fournisseurs — labels/couleurs sont
 * purement cosmétiques et ne servent jamais à décider si une action est autorisée (le backend décide). */

export const REQUEST_STATUS_LABEL: Record<PurchaseRequestStatut, string> = {
  demandee: "Demandée",
  validee: "Validée",
  rejetee: "Rejetée",
  commandee: "Commandée",
};

export const REQUEST_STATUS_BADGE: Record<PurchaseRequestStatut, NonNullable<BadgeProps["status"]>> = {
  demandee: "warning",
  validee: "success",
  rejetee: "danger",
  commandee: "accent",
};

export const ORDER_STATUS_LABEL: Record<PurchaseOrderStatut, string> = {
  brouillon: "Brouillon",
  en_attente_validation: "En attente de validation",
  validee: "Validée",
  envoyee: "Envoyée",
  recue_partielle: "Reçue partiellement",
  recue_totale: "Reçue totalement",
  annulee: "Annulée",
};

export const ORDER_STATUS_BADGE: Record<PurchaseOrderStatut, NonNullable<BadgeProps["status"]>> = {
  brouillon: "neutral",
  en_attente_validation: "warning",
  validee: "accent",
  envoyee: "accent2",
  recue_partielle: "warning",
  recue_totale: "success",
  annulee: "danger",
};
