import type { BadgeProps } from "@/components/ui/badge";
import type { ReferralStatut } from "@/types/api";

/** Mappings d'affichage uniquement pour l'écran Référencement inter-structures —
 * labels/couleurs sont purement cosmétiques et ne servent jamais à décider si une
 * action est autorisée (le backend, via les abort_unless()/abort_if() de
 * PatientReferralController, reste seul arbitre). */

export const REFERRAL_STATUT_LABEL: Record<ReferralStatut, string> = {
  envoye: "Envoyé",
  accepte: "Accepté",
  refuse: "Refusé",
  complete: "Complété",
};

export const REFERRAL_STATUT_BADGE: Record<ReferralStatut, NonNullable<BadgeProps["status"]>> = {
  envoye: "warning",
  accepte: "accent",
  refuse: "danger",
  complete: "success",
};
