import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { DirectionDashboard, FinancialDashboard, MedicalDashboard, QualiteDashboard } from "@/types/api";

export interface DashboardRangeFilters {
  from: string;
  to: string;
  siteId?: number | null;
}

/** Each dashboard section has its own permission on the backend (dashboards.medical/.financier/.direction/.qualite),
 * so each is fetched independently and can load/fail on its own without blocking the rest of the page. */
export function useDirectionDashboard({ from, to }: { from: string; to: string }) {
  return useQuery({
    queryKey: ["dashboards", "direction", from, to],
    queryFn: async () => {
      const { data } = await api.get<DirectionDashboard>("/dashboards/direction", { params: { from, to } });
      return data;
    },
  });
}

export function useFinancialDashboard({ from, to, siteId }: DashboardRangeFilters) {
  return useQuery({
    queryKey: ["dashboards", "financier", from, to, siteId],
    queryFn: async () => {
      const { data } = await api.get<FinancialDashboard>("/dashboards/financier", {
        params: { from, to, site_id: siteId ?? undefined },
      });
      return data;
    },
  });
}

export interface MedicalDashboardFilters extends DashboardRangeFilters {
  groupBy?: "code" | "chapter";
}

export function useMedicalDashboard({ from, to, siteId, groupBy }: MedicalDashboardFilters) {
  return useQuery({
    queryKey: ["dashboards", "medical", from, to, siteId, groupBy],
    queryFn: async () => {
      const { data } = await api.get<MedicalDashboard>("/dashboards/medical", {
        params: { from, to, site_id: siteId ?? undefined, group_by: groupBy },
      });
      return data;
    },
  });
}

export function useQualiteDashboard({ from, to, service }: { from: string; to: string; service?: string }) {
  return useQuery({
    queryKey: ["dashboards", "qualite", from, to, service],
    queryFn: async () => {
      const { data } = await api.get<QualiteDashboard>("/dashboards/qualite", { params: { from, to, service } });
      return data;
    },
  });
}
