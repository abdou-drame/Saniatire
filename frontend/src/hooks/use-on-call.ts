import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { OnCallEntry, WorkSchedule } from "@/types/api";

/**
 * Two distinct shapes from the backend: /on-call/now returns full
 * WorkSchedule resources (id, structure_id, created_at, ...) while /on-call
 * returns plain array items without those fields (see OnCallEntry) — do not
 * conflate the two.
 */
export function useOnCallNow(filters: { siteId?: number } = {}) {
  return useQuery({
    queryKey: ["on-call", "now", filters],
    queryFn: async () => {
      const { data } = await api.get<{ data: WorkSchedule[] }>("/on-call/now", {
        params: { site_id: filters.siteId },
      });
      return data.data;
    },
    refetchInterval: 60_000,
  });
}

export function useOnCallBetween(filters: { from: string; to: string; siteId?: number }) {
  return useQuery({
    queryKey: ["on-call", "between", filters],
    queryFn: async () => {
      const { data } = await api.get<{ data: OnCallEntry[] }>("/on-call", {
        params: { from: filters.from, to: filters.to, site_id: filters.siteId },
      });
      return data.data;
    },
  });
}
