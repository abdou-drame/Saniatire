import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { formatDate } from "@/lib/datetime";
import type { DialysisSession } from "@/types/specialty";

/**
 * Bespoke chart — the second genuinely special case, not a config-driven
 * field. Plots each session's post-dialysis weight against its dry-weight
 * target so drift is visible at a glance; never recomputes either value,
 * only renders what the API already returned.
 */
export function DryWeightChart({ sessions }: { sessions: DialysisSession[] }) {
  const data = [...sessions]
    .sort((a, b) => a.session_date.localeCompare(b.session_date))
    .filter((s) => s.post_weight_kg !== null || s.dry_weight_kg !== null)
    .map((s) => ({
      date: formatDate(s.session_date),
      poids_post: s.post_weight_kg,
      poids_sec: s.dry_weight_kg,
    }));

  if (data.length === 0) {
    return <p className="text-xs text-text-subtle">Pas encore assez de données pour tracer une courbe.</p>;
  }

  return (
    <div className="h-64 w-full">
      <ResponsiveContainer width="100%" height="100%">
        <LineChart data={data} margin={{ top: 8, right: 12, left: -12, bottom: 0 }}>
          <CartesianGrid strokeDasharray="3 3" stroke="var(--color-border)" />
          <XAxis dataKey="date" tick={{ fontSize: 11, fill: "var(--color-text-subtle)" }} />
          <YAxis tick={{ fontSize: 11, fill: "var(--color-text-subtle)" }} unit=" kg" width={56} />
          <Tooltip
            contentStyle={{
              backgroundColor: "var(--color-surface)",
              border: "1px solid var(--color-border)",
              borderRadius: 8,
              fontSize: 12,
            }}
          />
          <Line
            type="monotone"
            dataKey="poids_post"
            name="Poids post-dialyse"
            stroke="var(--color-accent)"
            strokeWidth={2}
            dot={{ r: 3 }}
            connectNulls
          />
          <Line
            type="monotone"
            dataKey="poids_sec"
            name="Poids sec cible"
            stroke="var(--color-accent2)"
            strokeWidth={2}
            strokeDasharray="4 4"
            dot={{ r: 3 }}
            connectNulls
          />
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}
