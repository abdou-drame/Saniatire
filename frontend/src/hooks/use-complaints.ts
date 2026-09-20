import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Complaint, ComplaintResponse, ComplaintStatut, Paginated } from "@/types/api";

function useInvalidateComplaints() {
  const queryClient = useQueryClient();
  return () => {
    // Préfixe unique ["complaints"] : couvre à la fois la liste (["complaints", filters])
    // et le détail (["complaints", id]) en un seul appel.
    queryClient.invalidateQueries({ queryKey: ["complaints"] });
  };
}

export function useComplaints(filters: { statut?: ComplaintStatut } = {}) {
  return useQuery({
    queryKey: ["complaints", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<Complaint>>("/complaints", {
        params: { statut: filters.statut },
      });
      return data;
    },
  });
}

/**
 * Seul endpoint (`GET /complaints/{id}`) où `responses` est chargé côté
 * backend (`$complaint->load('responses')`) — sur index/store/assign/
 * resolve/close, `responses` est absent ou vide. Ne jamais présumer sa
 * présence ailleurs que via ce hook.
 */
export function useComplaint(id: number | undefined) {
  return useQuery({
    queryKey: ["complaints", id],
    queryFn: async () => {
      const { data } = await api.get<{ data: Complaint }>(`/complaints/${id}`);
      return data.data;
    },
    enabled: Boolean(id),
  });
}

export interface CreateComplaintInput {
  patient_id: number;
  motif: string;
  description: string;
  service_concerne?: string;
}

// statut et gestionnaire_id ne sont jamais envoyés ici : statut est forcé à
// 'ouverte' côté serveur, gestionnaire_id n'est jamais définissable à la création.
export function useCreateComplaint() {
  const invalidate = useInvalidateComplaints();
  return useMutation({
    mutationFn: async (input: CreateComplaintInput) => {
      const { data } = await api.post<{ data: Complaint }>("/complaints", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

// Le backend est seul arbitre de la transition (abort_if statut !== 'ouverte'
// -> 422 "Seule une réclamation ouverte peut être assignée."). Ce hook ne
// pré-valide jamais le statut courant avant d'appeler l'API.
export function useAssignComplaint() {
  const invalidate = useInvalidateComplaints();
  return useMutation({
    mutationFn: async ({ id, gestionnaireId }: { id: number; gestionnaireId: number }) => {
      const { data } = await api.post<{ data: Complaint }>(`/complaints/${id}/assign`, {
        gestionnaire_id: gestionnaireId,
      });
      return data.data;
    },
    onSuccess: invalidate,
  });
}

// Retourne un ComplaintResponseResource seul (201), jamais un Complaint
// complet — l'historique des réponses n'est mis à jour qu'après refetch de
// la requête détail, assuré ici par l'invalidation du préfixe ["complaints"].
export function useRespondToComplaint() {
  const invalidate = useInvalidateComplaints();
  return useMutation({
    mutationFn: async ({
      id,
      message,
      visiblePatient,
    }: {
      id: number;
      message: string;
      visiblePatient?: boolean;
    }) => {
      const { data } = await api.post<{ data: ComplaintResponse }>(`/complaints/${id}/respond`, {
        message,
        visible_patient: visiblePatient,
      });
      return data.data;
    },
    onSuccess: invalidate,
  });
}

// Le backend rejette en 422 ("Seule une réclamation en cours peut être
// résolue.") si statut !== 'en_cours' — aucune vérification côté client.
export function useResolveComplaint() {
  const invalidate = useInvalidateComplaints();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ data: Complaint }>(`/complaints/${id}/resolve`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

// Le backend rejette en 422 ("Seule une réclamation résolue peut être
// clôturée.") si statut !== 'resolue' — aucune vérification côté client.
export function useCloseComplaint() {
  const invalidate = useInvalidateComplaints();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ data: Complaint }>(`/complaints/${id}/close`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}
