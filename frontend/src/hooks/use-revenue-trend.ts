import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";

export type TrendGranularity = "jour" | "semaine" | "mois" | "trimestre";

export interface TrendPoint {
  label: string;
  from: string;
  to: string;
  total: number;
}

function pad(n: number): string {
  return String(n).padStart(2, "0");
}

function dateKey(d: Date): string {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/**
 * Builds date-range buckets for the trend chart. This only slices calendar
 * time into windows to query — every `total` value plotted is the backend's
 * own `encaissements_total` for that exact window, fetched verbatim, never
 * summed or recomputed on the frontend.
 */
function buildBuckets(granularity: TrendGranularity): { from: string; to: string; label: string }[] {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const buckets: { from: string; to: string; label: string }[] = [];

  if (granularity === "jour") {
    for (let i = 13; i >= 0; i--) {
      const d = new Date(today);
      d.setDate(d.getDate() - i);
      buckets.push({
        from: dateKey(d),
        to: dateKey(d),
        label: d.toLocaleDateString("fr-FR", { day: "2-digit", month: "2-digit" }),
      });
    }
  } else if (granularity === "semaine") {
    for (let i = 7; i >= 0; i--) {
      const end = new Date(today);
      end.setDate(end.getDate() - i * 7);
      const start = new Date(end);
      start.setDate(start.getDate() - 6);
      buckets.push({
        from: dateKey(start),
        to: dateKey(end),
        label: start.toLocaleDateString("fr-FR", { day: "2-digit", month: "2-digit" }),
      });
    }
  } else if (granularity === "mois") {
    for (let i = 5; i >= 0; i--) {
      const start = new Date(today.getFullYear(), today.getMonth() - i, 1);
      const end = new Date(today.getFullYear(), today.getMonth() - i + 1, 0);
      buckets.push({
        from: dateKey(start),
        to: dateKey(end),
        label: start.toLocaleDateString("fr-FR", { month: "short", year: "2-digit" }),
      });
    }
  } else {
    const currentQuarter = Math.floor(today.getMonth() / 3);
    for (let i = 3; i >= 0; i--) {
      const q = currentQuarter - i;
      const year = today.getFullYear() + Math.floor(q / 4);
      const qIndex = ((q % 4) + 4) % 4;
      const start = new Date(year, qIndex * 3, 1);
      const end = new Date(year, qIndex * 3 + 3, 0);
      buckets.push({ from: dateKey(start), to: dateKey(end), label: `T${qIndex + 1} ${year}` });
    }
  }

  return buckets;
}

export function useRevenueTrend({ granularity, siteId }: { granularity: TrendGranularity; siteId?: number | null }) {
  const buckets = buildBuckets(granularity);
  const bucketsKey = buckets.map((b) => `${b.from}:${b.to}`).join(",");

  return useQuery({
    queryKey: ["dashboards", "financier-trend", bucketsKey, siteId],
    queryFn: async (): Promise<TrendPoint[]> => {
      return Promise.all(
        buckets.map(async (bucket) => {
          const { data } = await api.get<{ encaissements_total: number }>("/dashboards/financier", {
            params: { from: bucket.from, to: bucket.to, site_id: siteId ?? undefined },
          });
          return { ...bucket, total: data.encaissements_total };
        }),
      );
    },
  });
}
