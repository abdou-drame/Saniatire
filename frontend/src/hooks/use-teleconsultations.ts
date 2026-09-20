import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Consultation, Paginated, Teleconsultation } from "@/types/api";

function useInvalidateTeleconsultations() {
  const queryClient = useQueryClient();
  return () => {
    // Préfixe unique ["teleconsultations"] : couvre à la fois la liste
    // (["teleconsultations", filters]) et le détail (["teleconsultations", id]).
    queryClient.invalidateQueries({ queryKey: ["teleconsultations"] });
  };
}

/**
 * GET /teleconsultations n'a pas de filtre `statut` côté serveur (seul
 * `patient_id` est supporté, confirmé en lisant TeleconsultationController)
 * — ce hook parcourt donc toutes les pages du résultat déjà filtré serveur
 * par patient_id (patron `links.next` de useUsersDirectory dans
 * use-users-directory.ts), pour qu'un filtre par statut côté composant
 * puisse porter sur l'ensemble complet plutôt que sur la seule première page.
 */
async function fetchAllTeleconsultations(filters: { patientId?: number }): Promise<Teleconsultation[]> {
  const results: Teleconsultation[] = [];
  let page = 1;
  for (;;) {
    const { data } = await api.get<Paginated<Teleconsultation>>("/teleconsultations", {
      params: { patient_id: filters.patientId, page },
    });
    results.push(...data.data);
    if (!data.links.next) break;
    page++;
  }
  return results;
}

export function useTeleconsultations(filters: { patientId?: number } = {}) {
  return useQuery({
    queryKey: ["teleconsultations", filters],
    queryFn: () => fetchAllTeleconsultations(filters),
  });
}

export function useTeleconsultation(id: number) {
  return useQuery({
    queryKey: ["teleconsultations", id],
    queryFn: async () => {
      const { data } = await api.get<{ data: Teleconsultation }>(`/teleconsultations/${id}`);
      return data.data;
    },
    enabled: Boolean(id),
  });
}

export interface CreateTeleconsultationInput {
  appointment_id: number;
}

// site_id/patient_id/practitioner_id ne sont jamais envoyés ici : le backend
// les copie depuis l'Appointment référencé. statut est forcé à 'planifiee'.
export function useCreateTeleconsultation() {
  const invalidate = useInvalidateTeleconsultations();
  return useMutation({
    mutationFn: async (input: CreateTeleconsultationInput) => {
      const { data } = await api.post<{ data: Teleconsultation }>("/teleconsultations", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

// Le backend rejette en 422 ("Cette téléconsultation ne peut plus
// démarrer.") si statut ∈ [terminee, annulee] — aucune vérification côté
// client au-delà de l'affichage conditionnel du bouton.
export function useStartTeleconsultation() {
  const invalidate = useInvalidateTeleconsultations();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ data: Teleconsultation }>(`/teleconsultations/${id}/start`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

// Le backend rejette en 422 ("Cette téléconsultation est déjà clôturée.") si
// statut === 'terminee' — aucune vérification côté client.
export function useCancelTeleconsultation() {
  const invalidate = useInvalidateTeleconsultations();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ data: Teleconsultation }>(`/teleconsultations/${id}/cancel`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export interface CloseTeleconsultationInput {
  id: number;
  reason: string;
  history_of_illness?: string | null;
  clinical_exam?: string | null;
  recommendations?: string | null;
  referral?: string | null;
  follow_up_suggested_at?: string | null;
}

// Le backend renvoie un ConsultationResource (la NOUVELLE consultation créée
// et déjà clôturée), jamais un TeleconsultationResource — voir
// TeleconsultationController::close(). data.id / data.status ('terminee')
// se rapportent à cette Consultation, pas au statut de la Teleconsultation
// elle-même (mis à jour côté serveur — statut: 'terminee', consultation_id
// renseigné — mais non renvoyé par cet appel ; un refetch de
// useTeleconsultations/useTeleconsultation, déclenché par l'invalidation
// ci-dessous, est nécessaire pour l'observer). Le backend rejette en 422
// ("Cette téléconsultation est déjà clôturée ou annulée.") si
// statut ∈ [terminee, annulee] — aucune vérification côté client.
export function useCloseTeleconsultation() {
  const invalidate = useInvalidateTeleconsultations();
  return useMutation({
    mutationFn: async ({ id, ...input }: CloseTeleconsultationInput) => {
      const { data } = await api.post<{ data: Consultation }>(`/teleconsultations/${id}/close`, input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}
