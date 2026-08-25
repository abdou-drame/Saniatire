import type { BadgeProps } from "@/components/ui/badge";
import type { BiomedicalEquipmentStatut, MaintenanceStatut, MaintenanceType } from "@/types/api";

/** Mappings d'affichage uniquement pour les écrans Équipements biomédicaux — labels/couleurs sont
 * purement cosmétiques et ne servent jamais à décider si une action est autorisée (le backend décide). */

export const EQUIPMENT_STATUS_LABEL: Record<BiomedicalEquipmentStatut, string> = {
  en_service: "En service",
  en_maintenance: "En maintenance",
  hors_service: "Hors service",
  reforme: "Réformé",
};

export const EQUIPMENT_STATUS_BADGE: Record<BiomedicalEquipmentStatut, NonNullable<BadgeProps["status"]>> = {
  en_service: "success",
  en_maintenance: "warning",
  hors_service: "danger",
  reforme: "neutral",
};

export const MAINTENANCE_TYPE_LABEL: Record<MaintenanceType, string> = {
  preventive: "Préventive",
  corrective: "Corrective",
};

export const MAINTENANCE_STATUS_LABEL: Record<MaintenanceStatut, string> = {
  planifiee: "Planifiée",
  realisee: "Réalisée",
  annulee: "Annulée",
};

export const MAINTENANCE_STATUS_BADGE: Record<MaintenanceStatut, NonNullable<BadgeProps["status"]>> = {
  planifiee: "warning",
  realisee: "success",
  annulee: "danger",
};
