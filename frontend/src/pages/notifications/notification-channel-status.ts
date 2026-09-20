import type { BadgeProps } from "@/components/ui/badge";
import type { NotificationCanal } from "@/types/api";

/**
 * Réalité technique des canaux de notification — source de vérité :
 * `app/Domain/Notification/Channels/EmailChannel.php` (seul canal qui envoie
 * réellement, via le mailer Laravel configuré) et `SmsChannel.php` /
 * `WhatsAppChannel.php` / `PushChannel.php` (qui ne font que
 * `Log::info('[SMS simulé] ...')` / `'[WhatsApp simulé] ...'` /
 * `'[Push simulé] ...'` — aucun fournisseur réel n'est branché, ce sont des
 * points d'extension documentés mais non implémentés).
 *
 * Si un jour un de ces 3 canaux est réellement branché à un fournisseur,
 * relire ces fichiers Channels pour mettre à jour `functional` ici — ne
 * jamais présumer qu'un canal envoie réellement sans avoir vérifié son
 * fichier Channel correspondant.
 */
export const NOTIFICATION_CANAL_LABEL: Record<NotificationCanal, string> = {
  email: "E-mail",
  sms: "SMS",
  whatsapp: "WhatsApp",
  push: "Push",
};

/** true uniquement pour le canal qui envoie réellement un message aujourd'hui. */
export const NOTIFICATION_CANAL_FUNCTIONAL: Record<NotificationCanal, boolean> = {
  email: true,
  sms: false,
  whatsapp: false,
  push: false,
};

export const NOTIFICATION_CANAL_BADGE: Record<NotificationCanal, NonNullable<BadgeProps["status"]>> = {
  email: "success",
  sms: "warning",
  whatsapp: "warning",
  push: "warning",
};

export const NOTIFICATION_CANAL_STATUS_LABEL: Record<NotificationCanal, string> = {
  email: "Fonctionnel",
  sms: "Non branché — simulation uniquement",
  whatsapp: "Non branché — simulation uniquement",
  push: "Non branché — simulation uniquement",
};

/** Texte long, pour un encart d'avertissement (formulaire) — jamais juste le badge court. */
export const NOTIFICATION_CANAL_WARNING: Record<NotificationCanal, string | null> = {
  email: null,
  sms: "Ce canal n'est pas encore connecté à un fournisseur réel dans cette installation — aucun message ne sera réellement envoyé tant qu'il ne sera pas branché (le backend se contente de journaliser un envoi simulé).",
  whatsapp:
    "Ce canal n'est pas encore connecté à un fournisseur réel dans cette installation — aucun message ne sera réellement envoyé tant qu'il ne sera pas branché (le backend se contente de journaliser un envoi simulé).",
  push: "Ce canal n'est pas encore connecté à un fournisseur réel dans cette installation — aucun message ne sera réellement envoyé tant qu'il ne sera pas branché (le backend se contente de journaliser un envoi simulé).",
};
