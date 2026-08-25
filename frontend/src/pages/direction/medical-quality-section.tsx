import { AlertCircle, BedDouble, ClipboardList, Smile, Stethoscope } from "lucide-react";
import { useState } from "react";
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { KpiCard } from "@/components/ui/kpi-card";
import { KpiRowSkeleton } from "@/components/ui/loading-state";
import { Select } from "@/components/ui/select";
import { useMedicalDashboard, useQualiteDashboard } from "@/hooks/use-dashboards";
import { apiErrorMessage } from "@/lib/api-error";
import { formatFcfa, formatNumber, formatPercent } from "@/lib/format";
import type { IcdStatRow } from "@/types/api";

const CHART_GRID = "#242938";
const CHART_TICK = { fill: "#5C6478", fontSize: 11 };
const TOOLTIP_STYLE = {
  contentStyle: { background: "#12151D", border: "1px solid #242938", borderRadius: 8, fontSize: 12, color: "#F4F5F7" },
  labelStyle: { color: "#9AA1B2" },
};

const STATUT_META: Record<string, { label: string; status: "neutral" | "warning" | "accent" | "success" }> = {
  ouverte: { label: "Ouverte", status: "warning" },
  en_cours: { label: "En cours", status: "accent" },
  resolue: { label: "Résolue", status: "success" },
  close: { label: "Clôturée", status: "neutral" },
};

function icdLabel(row: IcdStatRow): string {
  return row.label ?? row.chapter_label ?? row.code ?? row.chapter_code ?? "—";
}

export interface MedicalQualitySectionProps {
  from: string;
  to: string;
  siteId: number | null;
}

export function MedicalQualitySection({ from, to, siteId }: MedicalQualitySectionProps) {
  const [groupBy, setGroupBy] = useState<"code" | "chapter">("code");
  const medicalQuery = useMedicalDashboard({ from, to, siteId, groupBy });
  const qualiteQuery = useQualiteDashboard({ from, to });

  const epidemiologieChartData = (medicalQuery.data?.epidemiologie.stats ?? [])
    .slice(0, 10)
    .map((row) => ({ label: icdLabel(row), total: row.total }));

  const occupationColumns: DataTableColumn<{ ward_id: number; ward_name: string; total_beds: number; occupied_beds: number; occupancy_rate: number }>[] = [
    { key: "ward_name", header: "Service", sortable: true, accessor: (r) => r.ward_name },
    { key: "total_beds", header: "Lits", align: "right", accessor: (r) => r.total_beds },
    { key: "occupied_beds", header: "Occupés", align: "right", accessor: (r) => r.occupied_beds },
    { key: "occupancy_rate", header: "Taux", align: "right", sortable: true, accessor: (r) => r.occupancy_rate, render: (r) => formatPercent(r.occupancy_rate) },
  ];

  const actesColumns: DataTableColumn<{ categorie: string; total_actes: number; montant_total: number }>[] = [
    { key: "categorie", header: "Catégorie", sortable: true, accessor: (r) => r.categorie },
    { key: "total_actes", header: "Actes", align: "right", sortable: true, accessor: (r) => r.total_actes },
    { key: "montant_total", header: "Montant", align: "right", sortable: true, accessor: (r) => r.montant_total, render: (r) => formatFcfa(r.montant_total) },
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Tableau de bord médical et qualité</CardTitle>
      </CardHeader>
      <CardContent className="space-y-6">
        {medicalQuery.isError ? (
          <ErrorState message={apiErrorMessage(medicalQuery.error)} onRetry={() => medicalQuery.refetch()} />
        ) : (
          <>
            <section>
              <div className="mb-3 flex items-center justify-between">
                <h4 className="text-sm font-medium text-text">Épidémiologie — top 10</h4>
                <Select value={groupBy} onChange={(e) => setGroupBy(e.target.value as "code" | "chapter")} className="w-40">
                  <option value="code">Par code CIM</option>
                  <option value="chapter">Par chapitre</option>
                </Select>
              </div>
              {medicalQuery.isLoading ? (
                <KpiRowSkeleton count={1} />
              ) : epidemiologieChartData.length === 0 ? (
                <EmptyState icon={Stethoscope} title="Aucun diagnostic enregistré" description="Aucune donnée épidémiologique sur la période." />
              ) : (
                <div className="h-64">
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={epidemiologieChartData} layout="vertical" margin={{ top: 4, right: 16, left: 8, bottom: 0 }}>
                      <CartesianGrid stroke={CHART_GRID} horizontal={false} />
                      <XAxis type="number" tick={CHART_TICK} tickLine={false} axisLine={{ stroke: CHART_GRID }} />
                      <YAxis type="category" dataKey="label" tick={CHART_TICK} tickLine={false} axisLine={false} width={140} />
                      <Tooltip {...TOOLTIP_STYLE} />
                      <Bar dataKey="total" fill="#3D7EFF" radius={[0, 4, 4, 0]} />
                    </BarChart>
                  </ResponsiveContainer>
                </div>
              )}
            </section>

            <section>
              <h4 className="mb-3 text-sm font-medium text-text">Taux d'occupation des lits par service</h4>
              <DataTable
                columns={occupationColumns}
                data={medicalQuery.data?.occupation_lits ?? []}
                rowKey={(r) => r.ward_id}
                isLoading={medicalQuery.isLoading}
                pageSize={6}
                emptyState={<EmptyState icon={BedDouble} title="Aucun service" description="Aucune donnée d'occupation disponible." />}
              />
            </section>

            <section>
              <h4 className="mb-3 text-sm font-medium text-text">Actes par spécialité (structure entière)</h4>
              <DataTable
                columns={actesColumns}
                data={medicalQuery.data?.actes_par_specialite.data ?? []}
                rowKey={(r) => r.categorie}
                isLoading={medicalQuery.isLoading}
                pageSize={6}
                emptyState={<EmptyState icon={Stethoscope} title="Aucun acte" description="Aucun acte facturé sur la période." />}
              />
            </section>
          </>
        )}

        <section className="border-t border-border pt-6">
          <h4 className="mb-3 text-sm font-medium text-text">Qualité</h4>
          {qualiteQuery.isError ? (
            <ErrorState message={apiErrorMessage(qualiteQuery.error)} onRetry={() => qualiteQuery.refetch()} />
          ) : qualiteQuery.isLoading ? (
            <KpiRowSkeleton count={3} />
          ) : (
            <div className="space-y-4">
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <KpiCard
                  label="Score satisfaction moyen"
                  value={qualiteQuery.data!.score_moyen_satisfaction ?? "—"}
                  unit={qualiteQuery.data!.score_moyen_satisfaction !== null ? "/ 10" : undefined}
                  icon={Smile}
                />
                <KpiCard
                  label="Délai moyen de résolution"
                  value={qualiteQuery.data!.delai_moyen_resolution_heures ?? "—"}
                  unit={qualiteQuery.data!.delai_moyen_resolution_heures !== null ? "h" : undefined}
                  icon={AlertCircle}
                />
                <KpiCard label="Réclamations sur la période" value={formatNumber(qualiteQuery.data!.reclamations.par_statut.reduce((s, r) => s + r.total, 0))} icon={ClipboardList} />
              </div>

              {qualiteQuery.data!.reclamations.par_statut.length === 0 ? (
                <EmptyState icon={ClipboardList} title="Aucune réclamation" description="Aucune réclamation enregistrée sur la période." />
              ) : (
                <div className="flex flex-wrap gap-2">
                  {qualiteQuery.data!.reclamations.par_statut.map((row) => {
                    const meta = STATUT_META[row.statut] ?? { label: row.statut, status: "neutral" as const };
                    return (
                      <Badge key={row.statut} status={meta.status}>
                        {meta.label} — {row.total}
                      </Badge>
                    );
                  })}
                </div>
              )}
            </div>
          )}
        </section>
      </CardContent>
    </Card>
  );
}
