import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { QualiteDashboard } from "@/types/api";

export type TrendGranularity = "jour" | "semaine" | "mois" | "trimestre";

export interface SatisfactionTrendPoint {
  label: string;
  from: string;
  to: string;
  score: number | null;
}

function pad(n: number): string {
  return String(n).padStart(2, "0");
}

function dateKey(d: Date): string {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/**
 * Buckets de dates dupliqués depuis use-revenue-trend.ts (délibérément, pas
 * factorisé dans un module partagé — voir le plan de l'étape 14 : toucher
 * use-revenue-trend.ts, déjà livré/validé sur l'écran financier, introduirait
 * un risque de régression pour un gain de mécanique interne uniquement).
 * Ceci ne fait que découper le calendrier en fenêtres à interroger — chaque
 * `score` tracé est le `score_moyen_satisfaction` propre du backend pour
 * cette fenêtre exacte, récupéré tel quel, jamais moyenné/recalculé côté
 * frontend. Un bucket sans donnée renvoie `null` (jamais substitué par 0) ;
 * recharts saute nativement les points `null` sur une Line.
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

export function useSatisfactionTrend({ granularity, service }: { granularity: TrendGranularity; service?: string }) {
  const buckets = buildBuckets(granularity);
  const bucketsKey = buckets.map((b) => `${b.from}:${b.to}`).join(",");

  return useQuery({
    queryKey: ["dashboards", "qualite-trend", bucketsKey, service],
    queryFn: async (): Promise<SatisfactionTrendPoint[]> => {
      return Promise.all(
        buckets.map(async (bucket) => {
          const { data } = await api.get<Pick<QualiteDashboard, "score_moyen_satisfaction">>("/dashboards/qualite", {
            params: { from: bucket.from, to: bucket.to, service },
          });
          return { ...bucket, score: data.score_moyen_satisfaction };
        }),
      );
    },
  });
}
