import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { platformApi } from "@/lib/platform-api";
import type { CreateStructureResponse, Paginated, Structure, StructureType } from "@/types/api";

function useInvalidatePlatformStructures() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: ["platform-structures"] });
  };
}

export function usePlatformStructures() {
  return useQuery({
    queryKey: ["platform-structures"],
    queryFn: async () => {
      const { data } = await platformApi.get<Paginated<Structure>>("/platform/structures");
      return data.data;
    },
  });
}

export function usePlatformStructure(id: number | undefined) {
  return useQuery({
    queryKey: ["platform-structures", id],
    queryFn: async () => {
      const { data } = await platformApi.get<{ data: Structure }>(`/platform/structures/${id}`);
      return data.data;
    },
    enabled: Boolean(id),
  });
}

export interface CreateStructureInput {
  code: string;
  legal_name: string;
  trade_name?: string;
  type: StructureType;
  address?: string;
  city?: string;
  country?: string;
  phone?: string;
  email?: string;
  registration_number?: string;
  tax_number?: string;
  currency?: string;
  locale?: string;
  admin_first_name: string;
  admin_last_name: string;
  admin_email: string;
}

/**
 * Crée la structure et son tout premier administrateur en un seul appel
 * (transaction unique côté backend). `admin_generated_password` n'est
 * présent que dans cette réponse — voir CreateStructureResponse.
 */
export function useCreatePlatformStructure() {
  const invalidate = useInvalidatePlatformStructures();
  return useMutation({
    mutationFn: async (input: CreateStructureInput) => {
      const { data } = await platformApi.post<CreateStructureResponse>("/platform/structures", input);
      return data;
    },
    onSuccess: invalidate,
  });
}

export function useActivatePlatformStructure() {
  const invalidate = useInvalidatePlatformStructures();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await platformApi.post<{ data: Structure }>(`/platform/structures/${id}/activate`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useDeactivatePlatformStructure() {
  const invalidate = useInvalidatePlatformStructures();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await platformApi.post<{ data: Structure }>(`/platform/structures/${id}/deactivate`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}
