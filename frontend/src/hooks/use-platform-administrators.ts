import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { platformApi } from "@/lib/platform-api";
import type { PlatformStaffUser, PlatformStaffUserWithPassword } from "@/types/api";

function administratorsKey(structureId: number | undefined) {
  return ["platform-structure-administrators", structureId] as const;
}

export function usePlatformStructureAdministrators(structureId: number | undefined) {
  return useQuery({
    queryKey: administratorsKey(structureId),
    queryFn: async () => {
      const { data } = await platformApi.get<{ data: PlatformStaffUser[] }>(
        `/platform/structures/${structureId}/administrators`,
      );
      return data.data;
    },
    enabled: Boolean(structureId),
  });
}

export interface CreateAdministratorInput {
  first_name: string;
  last_name: string;
  email: string;
}

export function useCreatePlatformAdministrator(structureId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateAdministratorInput) => {
      const { data } = await platformApi.post<PlatformStaffUserWithPassword>(
        `/platform/structures/${structureId}/administrators`,
        input,
      );
      return data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: administratorsKey(structureId) }),
  });
}

export function useSetPlatformAdministratorActive(structureId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ userId, active }: { userId: number; active: boolean }) => {
      const { data } = await platformApi.post<{ data: PlatformStaffUser }>(
        `/platform/structures/${structureId}/administrators/${userId}/${active ? "activate" : "deactivate"}`,
      );
      return data.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: administratorsKey(structureId) }),
  });
}

export function useUnlockPlatformUser(structureId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (userId: number) => {
      const { data } = await platformApi.post<{ data: PlatformStaffUser }>(`/platform/users/${userId}/unlock`);
      return data.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: administratorsKey(structureId) }),
  });
}

/** Personnel uniquement : révoque les sessions, exige un changement et débloque le compte. */
export function useResetPlatformUserPassword(structureId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (userId: number) => {
      const { data } = await platformApi.post<PlatformStaffUserWithPassword>(
        `/platform/users/${userId}/reset-password`,
      );
      return data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: administratorsKey(structureId) }),
  });
}
