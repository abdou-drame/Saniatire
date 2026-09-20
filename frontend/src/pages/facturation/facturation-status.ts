import type { BadgeProps } from "@/components/ui/badge";
import type { InvoiceStatus, QuoteStatut } from "@/types/api";

/** Mappings d'affichage uniquement pour l'écran Facturation — labels/couleurs sont
 * purement cosmétiques et ne servent jamais à décider si une action est autorisée (le backend décide). */

export const INVOICE_STATUS_LABEL: Record<InvoiceStatus, string> = {
  brouillon: "Brouillon",
  emise: "Émise",
  partiellement_payee: "Partiellement payée",
  payee: "Payée",
  annulee: "Annulée",
};

export const INVOICE_STATUS_BADGE: Record<InvoiceStatus, NonNullable<BadgeProps["status"]>> = {
  brouillon: "neutral",
  emise: "accent",
  partiellement_payee: "warning",
  payee: "success",
  annulee: "danger",
};

export const QUOTE_STATUS_LABEL: Record<QuoteStatut, string> = {
  brouillon: "Brouillon",
  emis: "Émis",
  converti: "Converti",
  expire: "Expiré",
  annule: "Annulé",
};

export const QUOTE_STATUS_BADGE: Record<QuoteStatut, NonNullable<BadgeProps["status"]>> = {
  brouillon: "neutral",
  emis: "accent",
  converti: "success",
  expire: "warning",
  annule: "danger",
};
