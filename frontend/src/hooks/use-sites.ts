import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated, Site } from "@/types/api";

/**
 * GET /sites est gardé par la permission `sites.view`. Les écrans ouverts à
 * tout utilisateur authentifié (ex. Gardes/astreintes) doivent donc passer
 * `enabled: hasPermission("sites.view")` pour ne pas déclencher un 403
 * inutile. Par défaut la requête reste activée, pour les écrans déjà
 * réservés à des rôles qui détiennent la permission.
 */
export function useSites(enabled = true) {
  return useQuery({
    queryKey: ["sites"],
    queryFn: async () => {
      const { data } = await api.get<Paginated<Site>>("/sites");
      return data.data;
    },
    staleTime: 5 * 60_000,
    enabled,
  });
}

export interface SiteInput {
  name: string;
  address?: string | null;
  city?: string | null;
  phone?: string | null;
  email?: string | null;
  opening_hours?: Record<string, unknown> | null;
  is_active?: boolean;
}

function useInvalidateSites() {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: ["sites"] });
}

export function useCreateSite() {
  const invalidate = useInvalidateSites();
  return useMutation({
    mutationFn: async (input: SiteInput) => {
      const { data } = await api.post<{ data: Site }>("/sites", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useUpdateSite() {
  const invalidate = useInvalidateSites();
  return useMutation({
    mutationFn: async ({ id, ...input }: SiteInput & { id: number }) => {
      const { data } = await api.patch<{ data: Site }>(`/sites/${id}`, input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useDeleteSite() {
  const invalidate = useInvalidateSites();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/sites/${id}`);
    },
    onSuccess: invalidate,
  });
}
