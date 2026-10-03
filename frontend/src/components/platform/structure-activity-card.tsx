import { Info, LoaderCircle } from "lucide-react";
import { useState } from "react";
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ErrorState } from "@/components/ui/error-state";
import { Skeleton } from "@/components/ui/loading-state";
import { Select } from "@/components/ui/select";
import { usePlatformStructureActivity } from "@/hooks/use-platform-insights";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDateTime } from "@/lib/datetime";
import { formatNumber } from "@/lib/format";

const PERIODS = [7, 30, 90] as const;

const CHART_GRID = "#242938";
const CHART_TICK = { fill: "#5C6478", fontSize: 11 };
const TOOLTIP_STYLE = {
  contentStyle: { background: "#12151D", border: "1px solid #242938", borderRadius: 8, fontSize: 12, color: "#F4F5F7" },
  labelStyle: { color: "#9AA1B2" },
};

/** "YYYY-MM-DD" → "03/10" (UTC, même convention que lib/datetime). */
function dayLabel(date: string): string {
  return new Date(`${date.slice(0, 10)}T00:00:00Z`).toLocaleDateString("fr-FR", {
    day: "2-digit",
    month: "2-digit",
    timeZone: "UTC",
  });
}

function plural(count: number, word: string): string {
  return `${word}${count > 1 ? "s" : ""}`;
}

function StatTile({ label, value, hint }: { label: string; value: string; hint?: string }) {
  return (
    <div className="min-w-0 rounded-lg border border-border bg-surface-hover/40 p-4">
      <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">{label}</p>
      <p className="mt-2 break-words font-heading font-tabular text-lg font-semibold text-text">{value}</p>
      {hint && <p className="mt-1 text-xs text-text-muted">{hint}</p>}
    </div>
  );
}

/**
 * Activité d'une structure vue depuis la plateforme : uniquement des
 * compteurs agrégés, jamais le contenu de son journal d'audit.
 */
export function StructureActivityCard({ structureId }: { structureId: number }) {
  const [days, setDays] = useState<number>(30);
  const activityQuery = usePlatformStructureActivity(structureId, days);
  const activity = activityQuery.data;

  const chartData = (activity?.actions_per_day ?? []).map((point) => ({ ...point, label: dayLabel(point.date) }));

  return (
    <Card>
      <CardHeader className="flex-wrap gap-2">
        <CardTitle className="flex items-center gap-2">
          Activité
          {activityQuery.isFetching && !activityQuery.isLoading && (
            <LoaderCircle size={14} className="animate-spin text-text-muted" />
          )}
        </CardTitle>
        <div className="w-36">
          <Select aria-label="Période" value={String(days)} onChange={(e) => setDays(Number(e.target.value))}>
            {PERIODS.map((value) => (
              <option key={value} value={value}>
                {value} jours
              </option>
            ))}
          </Select>
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        {activityQuery.isLoading ? (
          <>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
              {Array.from({ length: 3 }).map((_, i) => (
                <Skeleton key={i} className="h-20 w-full" />
              ))}
            </div>
            <Skeleton className="h-48 w-full" />
          </>
        ) : activityQuery.isError ? (
          <ErrorState message={apiErrorMessage(activityQuery.error)} onRetry={() => activityQuery.refetch()} />
        ) : activity ? (
          <>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
              <StatTile
                label="Dernière connexion"
                value={activity.last_login_at ? formatDateTime(activity.last_login_at) : "Jamais"}
              />
              <StatTile
                label="Utilisateurs connectés"
                value={`${formatNumber(activity.users_logged_in_period)} / ${formatNumber(activity.users_total)}`}
                hint={`sur ${activity.period.days} jours · ${formatNumber(activity.users_active)} ${plural(activity.users_active, "compte")} ${plural(activity.users_active, "actif")}`}
              />
              <StatTile
                label="Actions"
                value={formatNumber(activity.actions_total)}
                hint={`sur ${activity.period.days} jours`}
              />
            </div>

            <section>
              <h4 className="mb-2 text-sm font-medium text-text">Actions par jour</h4>
              <div className="h-48 min-w-0">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={chartData} margin={{ top: 4, right: 8, left: 0, bottom: 0 }}>
                    <CartesianGrid stroke={CHART_GRID} vertical={false} />
                    <XAxis
                      dataKey="label"
                      tick={CHART_TICK}
                      tickLine={false}
                      axisLine={{ stroke: CHART_GRID }}
                      minTickGap={12}
                    />
                    <YAxis tick={CHART_TICK} tickLine={false} axisLine={false} width={36} allowDecimals={false} />
                    <Tooltip {...TOOLTIP_STYLE} formatter={(v) => [formatNumber(Number(v)), "Actions"]} />
                    <Bar dataKey="count" fill="#3D7EFF" radius={[3, 3, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </section>
          </>
        ) : null}
        <p className="flex items-start gap-1.5 text-xs text-text-subtle">
          <Info size={13} className="mt-0.5 shrink-0" />
          Chiffres uniquement — le contenu des journaux de la structure n'est pas accessible à la plateforme.
        </p>
      </CardContent>
    </Card>
  );
}
