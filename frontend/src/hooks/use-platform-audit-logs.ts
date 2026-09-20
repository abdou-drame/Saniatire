import { useQuery } from "@tanstack/react-query";
import { platformApi } from "@/lib/platform-api";
import type { PlatformAuditLogPage } from "@/types/api";

export interface PlatformAuditLogFilters {
  structure_id?: number;
  from?: string;
  to?: string;
  page?: number;
}

export function usePlatformAuditLogs(filters: PlatformAuditLogFilters = {}) {
  return useQuery({
    queryKey: ["platform-audit-logs", filters],
    queryFn: async () => {
      const { data } = await platformApi.get<PlatformAuditLogPage>("/platform/audit-logs", {
        params: filters,
      });
      return data;
    },
  });
}
