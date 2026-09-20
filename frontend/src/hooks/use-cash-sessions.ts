import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useAuth } from "@/hooks/use-auth";
import { api } from "@/lib/api";
import type { CashSession, CashSessionStatut, Paginated } from "@/types/api";

function useInvalidateCaisse() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: ["cash-sessions"] });
    queryClient.invalidateQueries({ queryKey: ["payments"] });
  };
}

export function useCashSessions(
  filters: { statut?: CashSessionStatut; caissier_id?: number; site_id?: number } = {},
) {
  return useQuery({
    queryKey: ["cash-sessions", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<CashSession>>("/cash-sessions", {
        params: {
          statut: filters.statut,
          caissier_id: filters.caissier_id,
          site_id: filters.site_id,
        },
      });
      return data;
    },
  });
}

/**
 * Il n'existe pas d'endpoint dédié "ma session ouverte" côté backend : on
 * récupère donc toutes les sessions ouvertes (GET /cash-sessions?statut=ouverte)
 * et on filtre côté client sur caissier_id === utilisateur courant. C'est une
 * limitation connue documentée ici, pas un vrai endpoint "session courante".
 */
export function useCurrentCashSession() {
  const { user } = useAuth();

  return useQuery({
    queryKey: ["cash-sessions", "current"],
    queryFn: async () => {
      const { data } = await api.get<Paginated<CashSession>>("/cash-sessions", {
        params: { statut: "ouverte" },
      });
      // React Query interdit une valeur undefined depuis queryFn — null signifie
      // explicitement "aucune session en cours" (distinct d'un état de chargement).
      return data.data.find((session) => session.caissier_id === user?.id) ?? null;
    },
    enabled: Boolean(user),
  });
}

export interface OpenCashSessionInput {
  site_id: number;
  montant_ouverture: number;
}

export function useOpenCashSession() {
  const invalidate = useInvalidateCaisse();
  return useMutation({
    mutationFn: async (input: OpenCashSessionInput) => {
      const { data } = await api.post<{ data: CashSession }>("/cash-sessions", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export interface CloseCashSessionInput {
  id: number;
  montant_cloture: number;
}

export function useCloseCashSession() {
  const invalidate = useInvalidateCaisse();
  return useMutation({
    mutationFn: async ({ id, montant_cloture }: CloseCashSessionInput) => {
      const { data } = await api.post<{ data: CashSession }>(`/cash-sessions/${id}/close`, {
        montant_cloture,
      });
      return data.data;
    },
    onSuccess: invalidate,
  });
}
