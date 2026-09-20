import { AlertCircle, Clock, Smile } from "lucide-react";
import { useState } from "react";
import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ErrorState } from "@/components/ui/error-state";
import { Input } from "@/components/ui/input";
import { KpiCard } from "@/components/ui/kpi-card";
import { KpiRowSkeleton, TableSkeleton } from "@/components/ui/loading-state";
import { Select } from "@/components/ui/select";
import { useQualiteDashboard } from "@/hooks/use-dashboards";
import { useSatisfactionTrend, type TrendGranularity } from "@/hooks/use-satisfaction-trend";
import { apiErrorMessage } from "@/lib/api-error";
import type { ComplaintStatut } from "@/types/api";
import { COMPLAINT_STATUT_BADGE, COMPLAINT_STATUT_LABEL } from "@/pages/qualite/qualite-status";

// Constantes de style de graphique dupliquées localement — comme dans
// financial-dashboard-section.tsx, aucun module de thème de graphique
// partagé n'existe dans ce projet.
const CHART_GRID = "#242938";
const CHART_TICK = { fill: "#5C6478", fontSize: 11 };
const TOOLTIP_STYLE = {
  contentStyle: { background: "#12151D", border: "1px solid #242938", borderRadius: 8, fontSize: 12, color: "#F4F5F7" },
  labelStyle: { color: "#9AA1B2" },
};

const GRANULARITY_OPTIONS: { value: TrendGranularity; label: string }[] = [
  { value: "jour", label: "Jour" },
  { value: "semaine", label: "Semaine" },
  { value: "mois", label: "Mois" },
  { value: "trimestre", label: "Trimestre" },
];

function pad(n: number): string {
  return String(n).padStart(2, "0");
}

function dateKey(d: Date): string {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function defaultFrom(): string {
  const d = new Date();
  d.setMonth(d.getMonth() - 1);
  return dateKey(d);
}

function defaultTo(): string {
  return dateKey(new Date());
}

/** Formatage d'affichage uniquement (arrondi à 1 décimale) — jamais un
 * recalcul : la valeur vient telle quelle de score_moyen_satisfaction. */
function formatScore(value: number | null | undefined): string {
  return typeof value === "number" ? value.toLocaleString("fr-FR", { maximumFractionDigits: 1 }) : "—";
}

/** Idem pour le délai moyen de résolution — présentation seule. */
function formatDelai(value: number | null | undefined): string {
  return typeof value === "number" ? value.toLocaleString("fr-FR", { maximumFractionDigits: 1 }) : "—";
}

export function QualiteOverviewSection() {
  const [from, setFrom] = useState(defaultFrom());
  const [to, setTo] = useState(defaultTo());
  const [service, setService] = useState("");
  const [granularity, setGranularity] = useState<TrendGranularity>("mois");

  const trimmedService = service.trim() || undefined;
  const dashboardQuery = useQualiteDashboard({ from, to, service: trimmedService });
  const trendQuery = useSatisfactionTrend({ granularity, service: trimmedService });

  // Lecture directe d'un compte déjà calculé côté serveur (reclamations.par_statut),
  // jamais un dénombrement/calcul fait côté frontend — conforme à la règle de
  // non-recalcul des chiffres qualité.
  const reclamationsOuvertes = dashboardQuery.data?.reclamations.par_statut.find((r) => r.statut === "ouverte")?.total ?? 0;

  return (
    <Card>
      <CardHeader>
        <CardTitle>Vue d'ensemble qualité</CardTitle>
      </CardHeader>
      <CardContent className="space-y-6">
        <div className="flex flex-wrap items-end gap-3">
          <div>
            <label className="mb-1 block text-xs font-medium text-text-subtle">Du</label>
            <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-40" />
          </div>
          <div>
            <label className="mb-1 block text-xs font-medium text-text-subtle">Au</label>
            <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-40" />
          </div>
          <div>
            <label className="mb-1 block text-xs font-medium text-text-subtle">Service</label>
            <Input
              type="text"
              placeholder="Tous les services"
              value={service}
              onChange={(e) => setService(e.target.value)}
              className="w-48"
            />
          </div>
        </div>

        {dashboardQuery.isError ? (
          <ErrorState message={apiErrorMessage(dashboardQuery.error)} onRetry={() => dashboardQuery.refetch()} />
        ) : dashboardQuery.isLoading ? (
          <KpiRowSkeleton count={3} />
        ) : (
          <>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <KpiCard label="Score satisfaction moyen" value={formatScore(dashboardQuery.data?.score_moyen_satisfaction)} unit="/10" icon={Smile} />
              <KpiCard label="Réclamations ouvertes" value={reclamationsOuvertes} icon={AlertCircle} />
              <KpiCard
                label="Délai moyen de résolution"
                value={formatDelai(dashboardQuery.data?.delai_moyen_resolution_heures)}
                unit="h"
                icon={Clock}
              />
            </div>

            {(dashboardQuery.data?.reclamations.par_statut.length ?? 0) > 0 && (
              <div className="flex flex-wrap gap-2">
                {dashboardQuery.data!.reclamations.par_statut.map((r) => {
                  const knownStatut = r.statut as ComplaintStatut;
                  const label = COMPLAINT_STATUT_LABEL[knownStatut] ?? r.statut;
                  const status = COMPLAINT_STATUT_BADGE[knownStatut] ?? "neutral";
                  return (
                    <Badge key={r.statut} status={status}>
                      {label} ({r.total})
                    </Badge>
                  );
                })}
              </div>
            )}
          </>
        )}

        <section>
          <div className="mb-3 flex items-center justify-between">
            <h4 className="text-sm font-medium text-text">Évolution du score de satisfaction</h4>
            <Select value={granularity} onChange={(e) => setGranularity(e.target.value as TrendGranularity)} className="w-36">
              {GRANULARITY_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </Select>
          </div>
          {trendQuery.isError ? (
            <ErrorState message={apiErrorMessage(trendQuery.error)} onRetry={() => trendQuery.refetch()} />
          ) : trendQuery.isLoading ? (
            <div className="h-56">
              <TableSkeleton rows={1} columns={1} />
            </div>
          ) : (
            <div className="h-56">
              <ResponsiveContainer width="100%" height="100%">
                <LineChart data={trendQuery.data} margin={{ top: 4, right: 16, left: 0, bottom: 0 }}>
                  <CartesianGrid stroke={CHART_GRID} vertical={false} />
                  <XAxis dataKey="label" tick={CHART_TICK} tickLine={false} axisLine={{ stroke: CHART_GRID }} />
                  <YAxis tick={CHART_TICK} tickLine={false} axisLine={false} width={40} domain={[0, 10]} />
                  <Tooltip {...TOOLTIP_STYLE} formatter={(v) => (v === null ? "—" : `${v}/10`)} />
                  <Line type="monotone" dataKey="score" stroke="#3D7EFF" strokeWidth={2} dot={{ r: 3 }} connectNulls={false} />
                </LineChart>
              </ResponsiveContainer>
            </div>
          )}
        </section>
      </CardContent>
    </Card>
  );
}
