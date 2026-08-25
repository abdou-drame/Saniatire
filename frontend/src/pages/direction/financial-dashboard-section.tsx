import { useState } from "react";
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Line,
  LineChart,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { KpiRowSkeleton, TableSkeleton } from "@/components/ui/loading-state";
import { Select } from "@/components/ui/select";
import { useFinancialDashboard } from "@/hooks/use-dashboards";
import { useRevenueTrend, type TrendGranularity } from "@/hooks/use-revenue-trend";
import { apiErrorMessage } from "@/lib/api-error";
import { formatFcfa } from "@/lib/format";
import type { BalanceAgeeLigne, CreancesBucket } from "@/types/api";
import { Receipt } from "lucide-react";

const CHART_GRID = "#242938";
const CHART_TICK = { fill: "#5C6478", fontSize: 11 };
const TOOLTIP_STYLE = {
  contentStyle: { background: "#12151D", border: "1px solid #242938", borderRadius: 8, fontSize: 12, color: "#F4F5F7" },
  labelStyle: { color: "#9AA1B2" },
};

const DONUT_COLORS = ["#3D7EFF", "#8B6CF0", "#2FBF71", "#E8A93B", "#E4544C", "#6FA1FF", "#A894F5"];

const BUCKET_META: Record<CreancesBucket, { label: string; status: "neutral" | "warning" | "danger" }> = {
  "0-30": { label: "0-30 j", status: "neutral" },
  "31-60": { label: "31-60 j", status: "warning" },
  "61-90": { label: "61-90 j", status: "danger" },
  "90+": { label: "90+ j", status: "danger" },
};

const GRANULARITY_OPTIONS: { value: TrendGranularity; label: string }[] = [
  { value: "jour", label: "Jour" },
  { value: "semaine", label: "Semaine" },
  { value: "mois", label: "Mois" },
  { value: "trimestre", label: "Trimestre" },
];

export interface FinancialDashboardSectionProps {
  from: string;
  to: string;
  siteId: number | null;
}

export function FinancialDashboardSection({ from, to, siteId }: FinancialDashboardSectionProps) {
  const [granularity, setGranularity] = useState<TrendGranularity>("mois");
  const dashboardQuery = useFinancialDashboard({ from, to, siteId });
  const trendQuery = useRevenueTrend({ granularity, siteId });

  const balanceColumns: DataTableColumn<BalanceAgeeLigne>[] = [
    { key: "numero", header: "Facture", sortable: true, accessor: (r) => r.numero },
    { key: "date_emission", header: "Émise le", sortable: true, accessor: (r) => r.date_emission },
    { key: "anciennete_jours", header: "Ancienneté", align: "right", sortable: true, accessor: (r) => r.anciennete_jours },
    {
      key: "bucket",
      header: "Tranche",
      align: "right",
      render: (r) => {
        const meta = BUCKET_META[r.bucket];
        return (
          <Badge status={meta.status} className={r.bucket === "90+" ? "font-semibold" : undefined}>
            {r.bucket === "90+" ? "⚠ " : ""}
            {meta.label}
          </Badge>
        );
      },
    },
    { key: "solde", header: "Solde dû", align: "right", sortable: true, accessor: (r) => r.solde, render: (r) => formatFcfa(r.solde) },
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Tableau de bord financier</CardTitle>
      </CardHeader>
      <CardContent className="space-y-6">
        {dashboardQuery.isError && (
          <ErrorState message={apiErrorMessage(dashboardQuery.error)} onRetry={() => dashboardQuery.refetch()} />
        )}

        {!dashboardQuery.isError && (
          <>
            <section>
              <div className="mb-3 flex items-center justify-between">
                <h4 className="text-sm font-medium text-text">Évolution du chiffre d'affaires (encaissements)</h4>
                <Select
                  value={granularity}
                  onChange={(e) => setGranularity(e.target.value as TrendGranularity)}
                  className="w-36"
                >
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
                <div className="h-56"><TableSkeleton rows={1} columns={1} /></div>
              ) : (
                <div className="h-56">
                  <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={trendQuery.data} margin={{ top: 4, right: 16, left: 0, bottom: 0 }}>
                      <CartesianGrid stroke={CHART_GRID} vertical={false} />
                      <XAxis dataKey="label" tick={CHART_TICK} tickLine={false} axisLine={{ stroke: CHART_GRID }} />
                      <YAxis tick={CHART_TICK} tickLine={false} axisLine={false} width={48} />
                      <Tooltip {...TOOLTIP_STYLE} formatter={(v) => formatFcfa(Number(v))} />
                      <Line type="monotone" dataKey="total" stroke="#3D7EFF" strokeWidth={2} dot={{ r: 3 }} />
                    </LineChart>
                  </ResponsiveContainer>
                </div>
              )}
            </section>

            {dashboardQuery.isLoading ? (
              <KpiRowSkeleton count={2} />
            ) : (
              <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <section>
                  <h4 className="mb-3 text-sm font-medium text-text">Recettes facturées par prestation</h4>
                  {dashboardQuery.data!.recettes_facturees_par_prestation.length === 0 ? (
                    <EmptyState icon={Receipt} title="Aucune recette facturée" description="Aucune prestation facturée sur la période." />
                  ) : (
                    <div className="h-56">
                      <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                          <Pie
                            data={dashboardQuery.data!.recettes_facturees_par_prestation}
                            dataKey="montant_total"
                            nameKey="categorie"
                            innerRadius={45}
                            outerRadius={75}
                          >
                            {dashboardQuery.data!.recettes_facturees_par_prestation.map((entry, i) => (
                              <Cell key={entry.categorie} fill={DONUT_COLORS[i % DONUT_COLORS.length]} />
                            ))}
                          </Pie>
                          <Tooltip {...TOOLTIP_STYLE} formatter={(v) => formatFcfa(Number(v))} />
                        </PieChart>
                      </ResponsiveContainer>
                    </div>
                  )}
                </section>

                <section>
                  <h4 className="mb-3 text-sm font-medium text-text">Encaissements par mode de paiement</h4>
                  {dashboardQuery.data!.encaissements_par_mode_paiement.length === 0 ? (
                    <EmptyState icon={Receipt} title="Aucun encaissement" description="Aucun paiement enregistré sur la période." />
                  ) : (
                    <div className="h-56">
                      <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={dashboardQuery.data!.encaissements_par_mode_paiement} margin={{ top: 4, right: 16, left: 0, bottom: 0 }}>
                          <CartesianGrid stroke={CHART_GRID} vertical={false} />
                          <XAxis dataKey="mode_paiement" tick={CHART_TICK} tickLine={false} axisLine={{ stroke: CHART_GRID }} />
                          <YAxis tick={CHART_TICK} tickLine={false} axisLine={false} width={48} />
                          <Tooltip {...TOOLTIP_STYLE} formatter={(v) => formatFcfa(Number(v))} />
                          <Bar dataKey="total" fill="#8B6CF0" radius={[4, 4, 0, 0]} />
                        </BarChart>
                      </ResponsiveContainer>
                    </div>
                  )}
                </section>
              </div>
            )}

            <section>
              <h4 className="mb-3 text-sm font-medium text-text">Balance âgée des créances</h4>
              <DataTable
                columns={balanceColumns}
                data={dashboardQuery.data?.balance_agee.lignes ?? []}
                rowKey={(r) => r.invoice_id}
                isLoading={dashboardQuery.isLoading}
                pageSize={8}
                emptyState={
                  <EmptyState icon={Receipt} title="Aucune créance en cours" description="Toutes les factures de la période sont soldées." />
                }
              />
            </section>
          </>
        )}
      </CardContent>
    </Card>
  );
}
