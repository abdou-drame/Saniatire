import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated, PrescriberUser } from "@/types/api";

function useInvalidateExternalPrescribers() {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: ["external-prescribers"] });
}

// ExternalPrescriberController::index() pagine (paginate() par défaut,
// 15/page) sans filtre serveur — même patron que fetchAllUserAccounts :
// on parcourt toutes les pages pour que la liste affichée soit complète,
// la page n'ayant pas de contrôle de pagination propre.
async function fetchAllExternalPrescribers(): Promise<PrescriberUser[]> {
  const results: PrescriberUser[] = [];
  let page = 1;
  for (;;) {
    const { data } = await api.get<Paginated<PrescriberUser>>("/external-prescribers", { params: { page } });
    results.push(...data.data);
    if (!data.links.next) break;
    page++;
  }
  return results;
}

export function useExternalPrescribers() {
  return useQuery({
    queryKey: ["external-prescribers"],
    queryFn: fetchAllExternalPrescribers,
  });
}

export interface ExternalPrescriberInput {
  nom: string;
  specialite?: string;
  email: string;
  telephone?: string;
  statut?: "actif" | "inactif";
}

// structure_id n'est jamais envoyé : forcé côté serveur via BelongsToTenant
// (cf. ExternalPrescriber::fillable / abort du contrôleur), même patron que
// notification-templates.
export function useCreateExternalPrescriber() {
  const invalidate = useInvalidateExternalPrescribers();
  return useMutation({
    mutationFn: async (input: ExternalPrescriberInput) => {
      const { data } = await api.post<{ data: PrescriberUser }>("/external-prescribers", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useUpdateExternalPrescriber() {
  const invalidate = useInvalidateExternalPrescribers();
  return useMutation({
    mutationFn: async ({ id, ...input }: { id: number } & Partial<ExternalPrescriberInput>) => {
      const { data } = await api.put<{ data: PrescriberUser }>(`/external-prescribers/${id}`, input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useSendExternalPrescriberActivation() {
  const invalidate = useInvalidateExternalPrescribers();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ message: string }>(`/external-prescribers/${id}/portal/send-activation`);
      return data;
    },
    onSuccess: invalidate,
  });
}
