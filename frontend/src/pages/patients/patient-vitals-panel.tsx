import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { formatDate } from "@/lib/datetime";
import type { Consultation, TimelineEvent } from "@/types/api";
import { Activity } from "lucide-react";

function closedConsultationsAscending(events: TimelineEvent[]): Consultation[] {
  return events
    .filter((e) => e.type === "consultation")
    .map((e) => e.data as Consultation)
    .filter((c) => c.closed_at)
    .sort((a, b) => new Date(a.closed_at!).getTime() - new Date(b.closed_at!).getTime());
}

export function PatientVitalsPanel({ events }: { events: TimelineEvent[] }) {
  const consultations = closedConsultationsAscending(events);

  if (consultations.length === 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Constantes cliniques</CardTitle>
        </CardHeader>
        <CardContent>
          <EmptyState icon={Activity} title="Aucune constante enregistrée" />
        </CardContent>
      </Card>
    );
  }

  const latest = consultations[consultations.length - 1];

  if (consultations.length === 1) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Dernières constantes</CardTitle>
        </CardHeader>
        <CardContent className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
          <VitalItem label="Température" value={latest.vitals.temperature_c} unit="°C" />
          <VitalItem label="TA" value={bloodPressure(latest)} />
          <VitalItem label="FC" value={latest.vitals.heart_rate} unit="bpm" />
          <VitalItem label="SpO2" value={latest.vitals.spo2} unit="%" />
          <VitalItem label="Poids" value={latest.vitals.weight_kg} unit="kg" />
          <VitalItem label="IMC" value={latest.vitals.bmi} unit="kg/m²" />
        </CardContent>
      </Card>
    );
  }

  const trend = consultations
    .filter((c) => c.vitals.temperature_c !== null)
    .map((c) => ({ date: formatDate(c.closed_at!), temperature: c.vitals.temperature_c }));

  return (
    <Card>
      <CardHeader>
        <CardTitle>Température — historique</CardTitle>
      </CardHeader>
      <CardContent className="h-48 pl-0">
        {trend.length >= 2 ? (
          <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={trend} margin={{ top: 4, right: 16, left: 0, bottom: 0 }}>
              <defs>
                <linearGradient id="tempFill" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="#8B6CF0" stopOpacity={0.35} />
                  <stop offset="100%" stopColor="#8B6CF0" stopOpacity={0} />
                </linearGradient>
              </defs>
              <CartesianGrid stroke="#242938" vertical={false} />
              <XAxis dataKey="date" tick={{ fill: "#5C6478", fontSize: 11 }} tickLine={false} axisLine={{ stroke: "#242938" }} />
              <YAxis domain={["dataMin - 1", "dataMax + 1"]} tick={{ fill: "#5C6478", fontSize: 11 }} tickLine={false} axisLine={false} width={32} />
              <Tooltip
                contentStyle={{ background: "#12151D", border: "1px solid #242938", borderRadius: 8, fontSize: 12, color: "#F4F5F7" }}
                labelStyle={{ color: "#9AA1B2" }}
              />
              <Area type="monotone" dataKey="temperature" stroke="#8B6CF0" strokeWidth={2} fill="url(#tempFill)" />
            </AreaChart>
          </ResponsiveContainer>
        ) : (
          <div className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
            <VitalItem label="Température" value={latest.vitals.temperature_c} unit="°C" />
            <VitalItem label="TA" value={bloodPressure(latest)} />
            <VitalItem label="FC" value={latest.vitals.heart_rate} unit="bpm" />
          </div>
        )}
      </CardContent>
    </Card>
  );
}

function bloodPressure(consultation: Consultation): string | null {
  const { blood_pressure_systolic, blood_pressure_diastolic } = consultation.vitals;
  if (blood_pressure_systolic === null || blood_pressure_diastolic === null) return null;
  return `${blood_pressure_systolic}/${blood_pressure_diastolic}`;
}

function VitalItem({ label, value, unit }: { label: string; value: number | string | null; unit?: string }) {
  return (
    <div className="rounded-md border border-border px-3 py-2">
      <p className="text-[11px] text-text-subtle">{label}</p>
      <p className="font-tabular text-sm font-medium text-text">
        {value !== null ? value : "—"}
        {value !== null && unit && <span className="ml-1 text-xs text-text-muted">{unit}</span>}
      </p>
    </div>
  );
}
