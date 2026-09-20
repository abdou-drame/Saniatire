import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { platformApi } from "@/lib/platform-api";
import type { StructureModule } from "@/types/api";

/**
 * Cahier des charges §7 : uniquement la donnée et l'écran de bascule des
 * modules par structure — aucune autre partie de l'application n'est
 * branchée sur ce flag à ce stade, c'est normal (voir StructureModuleController).
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
    mutationFn: async ({ moduleId, isActive }: { moduleId: number; isActive: boolean }) => {
      const { data } = await platformApi.patch<{ data: StructureModule }>(
        `/platform/structures/${structureId}/modules/${moduleId}`,
        { is_active: isActive },
      );
      return data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["platform-structure-modules", structureId] });
    },
  });
}
