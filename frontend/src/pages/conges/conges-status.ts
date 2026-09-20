import type { BadgeProps } from "@/components/ui/badge";
import type { LeaveRequestStatut, LeaveRequestType, WorkScheduleType } from "@/types/api";

/** Mappings d'affichage uniquement pour l'écran Congés — labels/couleurs sont purement
 * cosmétiques et ne servent jamais à décider si une action est autorisée (le backend décide). */

export const LEAVE_STATUT_LABEL: Record<LeaveRequestStatut, string> = {
  demande: "Demandée",
  valide: "Validée",
  refuse: "Refusée",
};

export const LEAVE_STATUT_BADGE: Record<LeaveRequestStatut, NonNullable<BadgeProps["status"]>> = {
  demande: "warning",
  valide: "success",
  refuse: "danger",
};

export const LEAVE_TYPE_LABEL: Record<LeaveRequestType, string> = {
  conge_annuel: "Congé annuel",
  maladie: "Maladie",
  autre: "Autre",
};

/** Étiquettes françaises pour `schedule_type` tel que renvoyé dans les
 * warnings de chevauchement de `useValidateLeaveRequest` (voir
 * LeaveRequestOverlapWarning["schedule_type"] dans use-leave-requests.ts). */
export const SCHEDULE_TYPE_LABEL: Record<WorkScheduleType, string> = {
  normal: "normal",
  garde: "garde",
  astreinte: "astreinte",
};
