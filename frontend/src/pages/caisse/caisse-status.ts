import type { BadgeProps } from "@/components/ui/badge";
import type { CashSessionStatut, ModePaiement, StatutMobileMoney } from "@/types/api";

/** Mappings d'affichage uniquement pour l'écran Caisse — labels/couleurs sont purement
 * cosmétiques et ne servent jamais à décider si une action est autorisée (le backend décide),
 * ni à recalculer un montant ou un écart (toujours affiché tel que renvoyé par le serveur). */

export const CASH_SESSION_STATUS_LABEL: Record<CashSessionStatut, string> = {
  ouverte: "Ouverte",
  fermee: "Fermée",
};

export const CASH_SESSION_STATUS_BADGE: Record<CashSessionStatut, NonNullable<BadgeProps["status"]>> = {
  ouverte: "success",
  fermee: "neutral",
};

export const MODE_PAIEMENT_LABEL: Record<ModePaiement, string> = {
  especes: "Espèces",
  carte: "Carte",
  virement: "Virement",
  mobile_money: "Mobile money",
};

export const STATUT_MOBILE_MONEY_LABEL: Record<StatutMobileMoney, string> = {
  pending: "En attente",
  confirmed: "Confirmé",
  failed: "Échoué",
};

export const STATUT_MOBILE_MONEY_BADGE: Record<StatutMobileMoney, NonNullable<BadgeProps["status"]>> = {
  pending: "warning",
  confirmed: "success",
  failed: "danger",
};
