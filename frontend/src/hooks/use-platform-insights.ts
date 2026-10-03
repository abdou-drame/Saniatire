import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { platformApi } from "@/lib/platform-api";
import type { Paginated, PlatformStats, PlatformStructureActivity, PlatformStructureUser } from "@/types/api";

export function usePlatformStructureUsers(structureId: number | undefined, page: number) {
  return useQuery({
    queryKey: ["platform-structure-users", structureId, page],
    queryFn: async () => {
      const { data } = await platformApi.get<Paginated<PlatformStructureUser>>(
        `/platform/structures/${structureId}/users`,
        { params: { page } },
      );
      return data;
    },
    enabled: Boolean(structureId),
    placeholderData: keepPreviousData,
  });
}

export function usePlatformStructureActivity(structureId: number | undefined, days: number) {
  return useQuery({
    queryKey: ["platform-structure-activity", structureId, days],
    queryFn: async () => {
      const { data } = await platformApi.get<{ data: PlatformStructureActivity }>(
        `/platform/structures/${structureId}/activity`,
        { params: { days } },
      );
      return data.data;
    },
    enabled: Boolean(structureId),
    placeholderData: keepPreviousData,
  });
}

export function usePlatformStats() {
  return useQuery({
    queryKey: ["platform-stats"],
    queryFn: async () => {
      const { data } = await platformApi.get<{ data: PlatformStats }>("/platform/stats");
      return data.data;
    },
  });
}
