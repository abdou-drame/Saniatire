import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { StockLowThresholdAlert } from "@/types/api";

export function useLowThresholdStockAlerts(enabled = true) {
  return useQuery({
    queryKey: ["stock-alerts", "low-threshold"],
    queryFn: async () => {
      const { data } = await api.get<{ data: StockLowThresholdAlert[] }>("/stock/alerts/low-threshold");
      return data.data;
    },
    enabled,
  });
}
