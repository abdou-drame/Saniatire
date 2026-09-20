import type { BadgeProps } from "@/components/ui/badge";
import type { WorkScheduleType } from "@/types/api";

/**
 * Mappings d'affichage uniquement pour l'écran Plannings — labels/couleurs
 * sont purement cosmétiques et ne servent jamais à décider si un praticien
 * est disponible. Seul `PractitionerPresenceService::isPresent()` côté
 * backend fait autorité sur la disponibilité ; cet écran se contente
 * d'afficher les lignes WorkSchedule/PractitionerPlanning renvoyées par
 * l'API, sans jamais recalculer ou déduire une disponibilité.
 */

export const WORK_SCHEDULE_TYPE_LABEL: Record<WorkScheduleType, string> = {
  normal: "Normal",
  garde: "Garde",
  astreinte: "Astreinte",
};

/** Trois couleurs nettement distinctes, exigence explicite de l'écran Plannings. */
export const WORK_SCHEDULE_TYPE_BADGE: Record<WorkScheduleType, NonNullable<BadgeProps["status"]>> = {
  normal: "accent",
  garde: "danger",
  astreinte: "warning",
};

/**
 * Jours de la semaine pour le formulaire d'horaire récurrent. Valeurs
 * alignées sur la convention Carbon `dayOfWeek` utilisée par le backend
 * (0 = dimanche ... 6 = samedi) — voir `WorkSchedule::coversDay()` et
 * `PractitionerPresenceService`, qui comparent `jour_semaine` directement à
 * `$day->dayOfWeek`.
 */
export const WEEKDAY_OPTIONS: { value: number; label: string }[] = [
  { value: 1, label: "Lundi" },
  { value: 2, label: "Mardi" },
  { value: 3, label: "Mercredi" },
  { value: 4, label: "Jeudi" },
  { value: 5, label: "Vendredi" },
  { value: 6, label: "Samedi" },
  { value: 0, label: "Dimanche" },
];
