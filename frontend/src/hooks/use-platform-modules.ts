import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { platformApi } from "@/lib/platform-api";
import type { StructureModule } from "@/types/api";

/**
 * Livraison B : catalogue complet des modules d'une structure (socle +
 * premium), tel que calculé par le backend (ModuleCatalog). La bascule se
 * fait par clé de module ; un module du socle est refusé (422) côté serveur.
 */
export function usePlatformStructureModules(structureId: number | undefined) {
  return useQuery({
    queryKey: ["platform-structure-modules", structureId],
    queryFn: async () => {
      const { data } = await platformApi.get<{ data: StructureModule[] }>(
        `/platform/structures/${structureId}/modules`,
      );
      return data.data;
    },
    enabled: Boolean(structureId),
  });
}

export function useUpdatePlatformStructureModule(structureId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ module, isActive }: { module: string; isActive: boolean }) => {
      const { data } = await platformApi.patch<{ data: StructureModule }>(
        `/platform/structures/${structureId}/modules/${module}`,
        { is_active: isActive },
      );
      return data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["platform-structure-modules", structureId] });
    },
  });
}
