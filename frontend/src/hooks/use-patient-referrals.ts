import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated, PatientReferral, ReferralPatientResume } from "@/types/api";

function useInvalidatePatientReferrals() {
  const queryClient = useQueryClient();
  return () => {
    // Préfixe unique ["patient-referrals"] : couvre à la fois la liste
    // (["patient-referrals"]) et le détail (["patient-referrals", id]).
    queryClient.invalidateQueries({ queryKey: ["patient-referrals"] });
  };
}

/**
 * GET /patient-referrals n'a aucun paramètre de filtre côté serveur (ni
 * statut, ni structure — la visibilité est déjà bornée par
 * ReferralVisibilityScope à ce que l'utilisateur peut voir : origine OU
 * destination). Ce hook parcourt donc toutes les pages (patron `links.next`
 * de useUsersDirectory dans use-users-directory.ts) pour que la partition
 * envoyés/reçus, faite côté composant via user.structure_id, porte sur
 * l'ensemble complet plutôt que sur la seule première page.
 */
async function fetchAllPatientReferrals(): Promise<PatientReferral[]> {
  const results: PatientReferral[] = [];
  let page = 1;
  for (;;) {
    const { data } = await api.get<Paginated<PatientReferral>>("/patient-referrals", { params: { page } });
    results.push(...data.data);
    if (!data.links.next) break;
    page++;
  }
  return results;
}

export function usePatientReferrals() {
  return useQuery({
    queryKey: ["patient-referrals"],
    queryFn: fetchAllPatientReferrals,
  });
}

export function usePatientReferral(id: number) {
  return useQuery({
    queryKey: ["patient-referrals", id],
    queryFn: async () => {
      const { data } = await api.get<{ data: PatientReferral }>(`/patient-referrals/${id}`);
      return data.data;
    },
    enabled: Boolean(id),
  });
}

export interface CreatePatientReferralInput {
  structure_destination_id: number;
  site_origine_id?: number | null;
  patient_id: number;
  praticien_referent_id: number;
  motif: string;
}

// structure_origine_id n'est jamais envoyé ici : forcé côté serveur depuis
// user.structure_id. statut est forcé à 'envoye'.
export function useCreatePatientReferral() {
  const invalidate = useInvalidatePatientReferrals();
  return useMutation({
    mutationFn: async (input: CreatePatientReferralInput) => {
      const { data } = await api.post<{ data: PatientReferral }>("/patient-referrals", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

// Le backend rejette en 403 ("Seule la structure destinataire peut
// effectuer cette action.") si user.structure_id !== structure_destination_id
// — pas de garde sur le statut courant (pas de abort_if anti-réacceptation),
// donc ce hook n'en présume aucune : seul l'affichage conditionnel du
// bouton (statut === 'envoye') est un confort, jamais imposé par le serveur.
export function useAcceptPatientReferral() {
  const invalidate = useInvalidatePatientReferrals();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ data: PatientReferral }>(`/patient-referrals/${id}/accept`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

// Même garde abortUnlessDestination que accept — voir commentaire ci-dessus.
export function useRefusePatientReferral() {
  const invalidate = useInvalidatePatientReferrals();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ data: PatientReferral }>(`/patient-referrals/${id}/refuse`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export interface CompletePatientReferralInput {
  id: number;
  compte_rendu_retour: string;
}

// completeWithReport : même garde abortUnlessDestination. Un seul appel met
// à jour compte_rendu_retour ET statut: 'complete' — pas d'étape séparée.
export function useCompletePatientReferral() {
  const invalidate = useInvalidatePatientReferrals();
  return useMutation({
    mutationFn: async ({ id, ...input }: CompletePatientReferralInput) => {
      const { data } = await api.post<{ data: PatientReferral }>(`/patient-referrals/${id}/complete`, input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

// Contrairement à tous les autres endpoints de l'app, cette réponse n'est
// PAS enveloppée dans { data: ... } — c'est un objet JSON brut
// (voir PatientReferralController::patientResume). Ne pas faire .data.data.
// Endpoint sans middleware de permission, protégé uniquement par
// ReferralVisibilityScope (structure tierce => 404 sur l'appel parent, pas
// atteignable ici).
export function usePatientReferralResume(id: number, enabled = true) {
  return useQuery({
    queryKey: ["patient-referrals", id, "resume"],
    queryFn: async () => {
      const { data } = await api.get<ReferralPatientResume>(`/patient-referrals/${id}/patient-resume`);
      return data;
    },
    enabled: enabled && Boolean(id),
  });
}
