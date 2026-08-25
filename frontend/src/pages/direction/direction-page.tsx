import { Building2, Percent, PiggyBank, TrendingUp, Users } from "lucide-react";
import { useMemo, useState } from "react";
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input } from "@/components/ui/input";
import { KpiCard } from "@/components/ui/kpi-card";
import { KpiRowSkeleton } from "@/components/ui/loading-state";
import { Select } from "@/components/ui/select";
import { useDirectionDashboard, useFinancialDashboard } from "@/hooks/use-dashboards";
import { useSites } from "@/hooks/use-sites";
import { apiErrorMessage } from "@/lib/api-error";
import { formatFcfa, formatNumber, formatPercent, formatRatioAsPercent } from "@/lib/format";
import type { DirectionDashboard } from "@/types/api";
import { ExportButton } from "@/pages/direction/export-button";
import { FinancialDashboardSection } from "@/pages/direction/financial-dashboard-section";
import { MedicalQualitySection } from "@/pages/direction/medical-quality-section";

const CHART_GRID = "#242938";
const CHART_TICK = { fill: "#5C6478", fontSize: 11 };
const TOOLTIP_STYLE = {
  contentStyle: { background: "#12151D", border: "1px solid #242938", borderRadius: 8, fontSize: 12, color: "#F4F5F7" },
  labelStyle: { color: "#9AA1B2" },
};

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

type SiteComparisonRow = DirectionDashboard["comparaison_sites"][number];

export function DirectionPage() {
  const [siteId, setSiteId] = useState<number | null>(null);
  const [from, setFrom] = useState(defaultFrom());
  const [to, setTo] = useState(defaultTo());

  const sitesQuery = useSites();
  const directionQuery = useDirectionDashboard({ from, to });
  const financialQuery = useFinancialDashboard({ from, to, siteId });

  const selectedSiteRow = useMemo(
    () => directionQuery.data?.comparaison_sites.find((s) => s.site_id === siteId) ?? null,
    [directionQuery.data, siteId],
  );

  const consolide = directionQuery.data?.consolide;
  const kpiCa = siteId === null ? consolide?.ca_total : selectedSiteRow?.ca;
  const kpiPatients = siteId === null ? consolide?.nombre_patients : selectedSiteRow?.nombre_patients;
  const kpiOccupation = siteId === null ? (consolide?.taux_occupation_moyen ?? null) : (selectedSiteRow?.taux_occupation ?? null);

  const comparisonColumns: DataTableColumn<SiteComparisonRow>[] = [
    { key: "rang", header: "Rang", align: "right", accessor: (r) => r.rang },
    { key: "site_name", header: "Site", sortable: true, accessor: (r) => r.site_name },
    { key: "ca", header: "CA (encaissé)", align: "right", sortable: true, accessor: (r) => r.ca, render: (r) => formatFcfa(r.ca) },
    { key: "nombre_patients", header: "Patients", align: "right", sortable: true, accessor: (r) => r.nombre_patients },
    {
      key: "taux_occupation",
      header: "Occupation",
      align: "right",
      sortable: true,
      accessor: (r) => r.taux_occupation ?? 0,
      render: (r) => formatPercent(r.taux_occupation),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 className="font-heading text-xl font-semibold text-text">Direction — vue multi-sites</h2>
          <p className="mt-1 text-sm text-text-muted">Indicateurs consolidés de la structure et comparaison entre sites.</p>
        </div>
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
            <label className="mb-1 block text-xs font-medium text-text-subtle">Site</label>
            <Select
              value={siteId ?? "all"}
              onChange={(e) => setSiteId(e.target.value === "all" ? null : Number(e.target.value))}
              className="w-48"
            >
              <option value="all">Tous les sites</option>
              {(sitesQuery.data ?? []).map((site) => (
                <option key={site.id} value={site.id}>
                  {site.name}
                </option>
              ))}
            </Select>
          </div>
        </div>
      </div>

      {directionQuery.isError ? (
        <ErrorState message={apiErrorMessage(directionQuery.error)} onRetry={() => directionQuery.refetch()} />
      ) : directionQuery.isLoading ? (
        <KpiRowSkeleton count={4} />
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard label="Chiffre d'affaires (encaissé)" value={kpiCa !== undefined && kpiCa !== null ? formatFcfa(kpiCa) : "—"} icon={PiggyBank} />
          <KpiCard label="Patients (période)" value={kpiPatients !== undefined && kpiPatients !== null ? formatNumber(kpiPatients) : "—"} icon={Users} />
          <KpiCard label="Taux d'occupation moyen" value={formatPercent(kpiOccupation ?? null)} icon={Building2} />
          <KpiCard
            label="Taux de recouvrement"
            value={financialQuery.isLoading ? "…" : formatRatioAsPercent(financialQuery.data?.taux_recouvrement ?? null)}
            icon={Percent}
          />
        </div>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Comparaison entre sites</CardTitle>
        </CardHeader>
        <CardContent className="space-y-6">
          {directionQuery.isError ? (
            <ErrorState message={apiErrorMessage(directionQuery.error)} onRetry={() => directionQuery.refetch()} />
          ) : directionQuery.isLoading ? (
            <KpiRowSkeleton count={1} />
          ) : (directionQuery.data?.comparaison_sites.length ?? 0) === 0 ? (
            <EmptyState icon={TrendingUp} title="Aucun site" description="Aucun site n'est configuré pour cette structure." />
          ) : (
            <>
              <div className="h-56">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={directionQuery.data!.comparaison_sites} margin={{ top: 4, right: 16, left: 0, bottom: 0 }}>
                    <CartesianGrid stroke={CHART_GRID} vertical={false} />
                    <XAxis dataKey="site_name" tick={CHART_TICK} tickLine={false} axisLine={{ stroke: CHART_GRID }} />
                    <YAxis tick={CHART_TICK} tickLine={false} axisLine={false} width={48} />
                    <Tooltip {...TOOLTIP_STYLE} formatter={(v) => formatFcfa(Number(v))} />
                    <Bar dataKey="ca" fill="#3D7EFF" radius={[4, 4, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </div>
              <DataTable
                columns={comparisonColumns}
                data={directionQuery.data!.comparaison_sites}
                rowKey={(r) => r.site_id}
                pageSize={10}
              />
            </>
          )}
        </CardContent>
      </Card>

      <FinancialDashboardSection from={from} to={to} siteId={siteId} />
      <MedicalQualitySection from={from} to={to} siteId={siteId} />

      <Card>
        <CardHeader>
          <CardTitle>Exports</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-wrap gap-3">
          <ExportButton
            label="Épidémiologie (CSV)"
            url="/reports/epidemiologie/export"
            params={{ from, to, site_id: siteId ?? undefined }}
            filename="epidemiologie.csv"
          />
          <ExportButton
            label="Chiffre d'affaires (CSV)"
            url="/reports/chiffre-affaires/export"
            params={{ from, to }}
            filename="chiffre-affaires.csv"
          />
          <ExportButton
            label="Balance âgée (CSV)"
            url="/reports/balance-agee/export"
            params={{}}
            filename="balance-agee.csv"
          />
        </CardContent>
      </Card>
    </div>
  );
}
