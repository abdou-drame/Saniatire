import { CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { formatDate } from "@/lib/datetime";
import type { PediatricGrowthMeasurement } from "@/types/specialty";

/**
 * Bespoke chart — the specific requirement for pédiatrie, built alongside
 * (not instead of) the generic form. Plots weight (left axis, kg) against
 * height and head circumference (right axis, cm) over time. Never
 * recomputes a value, only renders what the API already returned; it
 * re-renders automatically whenever `measurements` changes because it's a
 * plain prop, not local state.
 */
export function GrowthChart({ measurements }: { measurements: PediatricGrowthMeasurement[] }) {
  const data = [...measurements]
    .sort((a, b) => a.measured_at.localeCompare(b.measured_at))
    .map((m) => ({
      date: formatDate(m.measured_at),
      poids: m.weight_kg,
      taille: m.height_cm,
      perimetre_cranien: m.head_circumference_cm,
    }));

  if (data.length === 0) {
    return <p className="text-xs text-text-subtle">Pas encore assez de données pour tracer une courbe.</p>;
  }

  return (
    <div className="h-72 w-full">
      <ResponsiveContainer width="100%" height="100%">
        <LineChart data={data} margin={{ top: 8, right: 12, left: -12, bottom: 0 }}>
          <CartesianGrid strokeDasharray="3 3" stroke="var(--color-border)" />
          <XAxis dataKey="date" tick={{ fontSize: 11, fill: "var(--color-text-subtle)" }} />
          <YAxis
            yAxisId="poids"
            tick={{ fontSize: 11, fill: "var(--color-text-subtle)" }}
            unit=" kg"
            width={56}
          />
          <YAxis
            yAxisId="taille"
            orientation="right"
            tick={{ fontSize: 11, fill: "var(--color-text-subtle)" }}
            unit=" cm"
            width={56}
          />
          <Tooltip
            contentStyle={{
              backgroundColor: "var(--color-surface)",
              border: "1px solid var(--color-border)",
              borderRadius: 8,
              fontSize: 12,
            }}
          />
          <Legend wrapperStyle={{ fontSize: 12 }} />
          <Line
            yAxisId="poids"
            type="monotone"
            dataKey="poids"
            name="Poids (kg)"
            stroke="var(--color-accent)"
            strokeWidth={2}
            dot={{ r: 3 }}
            connectNulls
          />
          <Line
            yAxisId="taille"
            type="monotone"
            dataKey="taille"
            name="Taille (cm)"
            stroke="var(--color-accent2)"
            strokeWidth={2}
            dot={{ r: 3 }}
            connectNulls
          />
          <Line
            yAxisId="taille"
            type="monotone"
            dataKey="perimetre_cranien"
            name="Périmètre crânien (cm)"
            stroke="var(--color-success)"
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
