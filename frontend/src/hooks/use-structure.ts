import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useAuth } from "@/hooks/use-auth";
import { api } from "@/lib/api";
import type { Structure } from "@/types/api";

/**
 * StructureController::index ne renvoie que la structure de l'appelant, mais
 * show/update exigent l'id en route — on utilise donc user.structure_id ici,
 * jamais un id saisi ou une liste (authorizeOwnStructure() renverrait 404
 * sur toute autre structure).
 */
export function useStructure() {
  const { user } = useAuth();
  return useQuery({
    queryKey: ["structure", user?.structure_id],
    queryFn: async () => {
      const { data } = await api.get<{ data: Structure }>(`/structures/${user!.structure_id}`);
      return data.data;
    },
    enabled: Boolean(user),
  });
}

export interface UpdateStructureInput {
  code: string;
  legal_name: string;
  trade_name?: string | null;
  type: Structure["type"];
  address?: string | null;
  city?: string | null;
  country?: string | null;
  phone?: string | null;
  email?: string | null;
  opening_hours?: Record<string, unknown> | null;
  registration_number?: string | null;
  tax_number?: string | null;
  color_primary?: string | null;
  color_secondary?: string | null;
  currency?: string | null;
  locale?: string | null;
  is_active?: boolean;
}

export function useUpdateStructure() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: UpdateStructureInput) => {
      const { data } = await api.patch<{ data: Structure }>(`/structures/${user!.structure_id}`, input);
      return data.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["structure"] }),
  });
}
