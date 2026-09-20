import type { BadgeProps } from "@/components/ui/badge";
import type { CreancesBucket } from "@/types/api";

/** Mappings d'affichage uniquement pour l'écran Créances — labels/couleurs sont purement
 * cosmétiques et n'affectent jamais le calcul des buckets, qui reste entièrement côté backend. */

export const BUCKET_LABEL: Record<CreancesBucket, string> = {
  "0-30": "0-30 jours",
  "31-60": "31-60 jours",
  "61-90": "61-90 jours",
  "90+": "Plus de 90 jours",
};

// Le Badge ne propose que 6 statuts sémantiques (success/warning/danger/
// accent/accent2/neutral) pour 4 niveaux d'urgence croissante attendus ici.
// accent2 est réutilisé comme "orange" intermédiaire entre warning (jaune)
// et danger (rouge) pour garder une progression visuelle strictement
// croissante sur les 4 buckets : succès -> avertissement -> accent2 (orange)
// -> danger.
export const BUCKET_BADGE: Record<CreancesBucket, NonNullable<BadgeProps["status"]>> = {
  "0-30": "success",
  "31-60": "warning",
  "61-90": "accent2",
  "90+": "danger",
};

export const BUCKET_COLOR_CLASS: Record<CreancesBucket, string> = {
  "0-30": "text-success",
  "31-60": "text-warning",
  "61-90": "text-accent2-light",
  "90+": "text-danger",
};
