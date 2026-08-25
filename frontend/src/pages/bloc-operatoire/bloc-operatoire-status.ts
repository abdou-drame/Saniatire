import type { BadgeProps } from "@/components/ui/badge";
import type { SurgicalChecklistStep, SurgicalProcedureStatus } from "@/types/api";

/** Shared display-only mappings for the Bloc opératoire screens — labels/colors are purely
 * cosmetic and never used to decide whether an action is allowed (the backend does). */

export const PROCEDURE_STATUS_LABEL: Record<SurgicalProcedureStatus, string> = {
  planifiee: "Planifiée",
  en_cours: "En cours",
  terminee: "Terminée",
  annulee: "Annulée",
};

export const PROCEDURE_STATUS_BADGE: Record<SurgicalProcedureStatus, NonNullable<BadgeProps["status"]>> = {
  planifiee: "neutral",
  en_cours: "accent",
  terminee: "success",
  annulee: "danger",
};

export const CHECKLIST_STEP_LABEL: Record<SurgicalChecklistStep, string> = {
  avant_anesthesie: "Avant anesthésie",
  avant_incision: "Avant incision",
  avant_sortie_bloc: "Avant sortie de bloc",
};

/** Contenu de vérification affiché pour chaque étape — texte fixe côté UI,
 * le backend n'impose aucun schéma particulier pour `items`. */
export const CHECKLIST_STEP_ITEMS: Record<SurgicalChecklistStep, string[]> = {
  avant_anesthesie: [
    "Identité du patient confirmée",
    "Site opératoire confirmé et marqué si applicable",
    "Consentement signé",
    "Allergies connues vérifiées",
    "Matériel et médicaments d'anesthésie vérifiés",
  ],
  avant_incision: [
    "Équipe complète présentée (nom et rôle)",
    "Confirmation verbale patient/site/intervention",
    "Antibioprophylaxie administrée si nécessaire",
    "Imagerie essentielle disponible et affichée",
  ],
  avant_sortie_bloc: [
    "Nom de l'intervention réalisée confirmé",
    "Comptage instruments/compresses/aiguilles correct",
    "Étiquetage des prélèvements vérifié",
    "Problèmes matériels à signaler identifiés",
    "Consignes de suivi communiquées à l'équipe",
  ],
};
