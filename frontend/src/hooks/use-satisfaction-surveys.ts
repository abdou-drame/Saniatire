import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated, PatientSatisfactionSurvey } from "@/types/api";

/**
 * GET /patient-satisfaction-surveys n'a pas de filtre de période côté
 * backend (uniquement `service` et `patient_id`, confirmé en lisant
 * PatientSatisfactionSurveyController::index) — ce hook parcourt donc
 * toutes les pages du résultat déjà filtré serveur par service/patient_id
 * (patron `links.next` de useUsersDirectory dans use-users-directory.ts),
 * pour que le composant consommateur puisse appliquer un filtre de date
 * en mémoire sur l'ensemble complet plutôt que sur la seule première page.
 * Ce filtre de date reste un filtre d'affichage pur : aucune moyenne ni
 * aucun autre agrégat n'est jamais dérivé de cet ensemble ici ou côté
 * composant — le score moyen affiché dans la Vue d'ensemble vient
 * exclusivement de `score_moyen_satisfaction` renvoyé par
 * GET /dashboards/qualite.
 */
async function fetchAllSurveys(filters: {
  service?: string;
  patientId?: number;
}): Promise<PatientSatisfactionSurvey[]> {
  const results: PatientSatisfactionSurvey[] = [];
  let page = 1;
  for (;;) {
    const { data } = await api.get<Paginated<PatientSatisfactionSurvey>>("/patient-satisfaction-surveys", {
      params: { service: filters.service, patient_id: filters.patientId, page },
    });
    results.push(...data.data);
    if (!data.links.next) break;
    page++;
  }
  return results;
}

export function useSatisfactionSurveys(filters: { service?: string; patientId?: number } = {}) {
  return useQuery({
    queryKey: ["patient-satisfaction-surveys", filters],
    queryFn: () => fetchAllSurveys(filters),
  });
}
