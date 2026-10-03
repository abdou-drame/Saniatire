import { Activity, Building2, Stethoscope, Users } from "lucide-react";
import { Link } from "react-router-dom";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { KpiCard } from "@/components/ui/kpi-card";
import { KpiRowSkeleton } from "@/components/ui/loading-state";
import { usePlatformStats } from "@/hooks/use-platform-insights";
import { apiErrorMessage } from "@/lib/api-error";
import { formatNumber } from "@/lib/format";
import type { PlatformStatsStructure } from "@/types/api";

/** "2026-05" → "mai 26" (abréviation française, année sur deux chiffres). */
function formatMonthShort(month: string): string {
  const [year, monthNumber] = month.split("-").map(Number);
  return new Date(Date.UTC(year, monthNumber - 1, 1)).toLocaleDateString("fr-FR", {
    month: "short",
    year: "2-digit",
    timeZone: "UTC",
  });
}

function monthCount(row: PlatformStatsStructure, month: string): number {
  return row.consultations_per_month.find((m) => m.month === month)?.count ?? 0;
}

function StructureStatusBadge({ structure }: { structure: PlatformStatsStructure }) {
  if (structure.is_archived) return <Badge status="danger">Archivée</Badge>;
  return structure.is_active ? <Badge status="success">Active</Badge> : <Badge status="neutral">Inactive</Badge>;
}

/**
 * Vue d'ensemble de la plateforme : volumes agrégés par structure, sans
 * accès au contenu des dossiers.
 */
export function PlatformStatsPage() {
  const statsQuery = usePlatformStats();
  const stats = statsQuery.data;

  const monthColumns: DataTableColumn<PlatformStatsStructure>[] = (stats?.months ?? []).map((month) => ({
    key: `month-${month}`,
    header: formatMonthShort(month),
    align: "right",
    sortable: true,
    accessor: (row) => monthCount(row, month),
    render: (row) => formatNumber(monthCount(row, month)),
  }));

  const nameColumn: DataTableColumn<PlatformStatsStructure> = {
    key: "legal_name",
    header: "Structure",
    sortable: true,
    accessor: (row) => row.legal_name,
    render: (row) => (
      <Link to={`/platform/structures/${row.id}`} className="whitespace-nowrap text-accent-light hover:underline">
        {row.legal_name}
      </Link>
    ),
  };

  const currentMonth = stats?.months[stats.months.length - 1];

  // Deux tableaux plutôt qu'un : avec les six colonnes mensuelles, un seul
  // tableau dépassait la largeur de la mise en page plateforme (max-w-4xl)
  // et masquait justement les mois les plus récents derrière un défilement.
  const columns: DataTableColumn<PlatformStatsStructure>[] = [
    nameColumn,
    { key: "status", header: "Statut", render: (row) => <StructureStatusBadge structure={row} /> },
    {
      key: "users_active_30d",
      header: "Utilisateurs actifs 30 j",
      align: "right",
      sortable: true,
      accessor: (row) => row.users_active_30d,
      render: (row) => formatNumber(row.users_active_30d),
    },
    {
      key: "patients",
      header: "Patients",
      align: "right",
      sortable: true,
      accessor: (row) => row.patients,
      render: (row) => formatNumber(row.patients),
    },
    {
      key: "consultations_this_month",
      header: "Consultations ce mois",
      align: "right",
      sortable: true,
      accessor: (row) => (currentMonth ? monthCount(row, currentMonth) : 0),
      render: (row) => formatNumber(currentMonth ? monthCount(row, currentMonth) : 0),
    },
  ];

  const monthlyColumns: DataTableColumn<PlatformStatsStructure>[] = [nameColumn, ...monthColumns];

  if (statsQuery.isError) {
    return (
      <div className="space-y-6">
        <StatsHeader />
        <ErrorState message={apiErrorMessage(statsQuery.error)} onRetry={() => statsQuery.refetch()} />
      </div>
    );
  }

  const structuresTotals = stats?.totals.structures;

  return (
    <div className="space-y-6">
      <StatsHeader />

      {!stats || !structuresTotals ? (
        <KpiRowSkeleton count={4} />
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          {/* Même gabarit que KpiCard, avec la répartition par statut en dessous. */}
          <Card className="p-5">
            <div className="flex items-start justify-between">
              <span className="text-xs font-medium uppercase tracking-wide text-text-subtle">Structures</span>
              <div className="rounded-md bg-surface-hover p-1.5 text-text-muted">
                <Building2 size={16} strokeWidth={2} />
              </div>
            </div>
            <p className="mt-3 font-heading font-tabular text-2xl font-semibold text-text">
              {formatNumber(structuresTotals.total)}
            </p>
            <div data-testid="structures-breakdown" className="mt-2 flex flex-wrap gap-1.5">
              <Badge status="success">
                {formatNumber(structuresTotals.active)} active{structuresTotals.active > 1 ? "s" : ""}
              </Badge>
              <Badge status="neutral">
                {formatNumber(structuresTotals.inactive)} inactive{structuresTotals.inactive > 1 ? "s" : ""}
              </Badge>
              <Badge status="danger">
                {formatNumber(structuresTotals.archived)} archivée{structuresTotals.archived > 1 ? "s" : ""}
              </Badge>
            </div>
          </Card>
          <KpiCard label="Utilisateurs actifs 30 j" value={formatNumber(stats.totals.users_active_30d)} icon={Activity} />
          <KpiCard label="Patients" value={formatNumber(stats.totals.patients)} icon={Users} />
          <KpiCard
            label="Consultations ce mois"
            value={formatNumber(stats.totals.consultations_this_month)}
            icon={Stethoscope}
          />
        </div>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Par structure</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable
            columns={columns}
            data={stats?.structures ?? []}
            rowKey={(row) => row.id}
            isLoading={statsQuery.isLoading}
            pageSize={20}
            emptyState={<EmptyState icon={Building2} title="Aucune structure" />}
          />
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Consultations par mois</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable
            columns={monthlyColumns}
            data={stats?.structures ?? []}
            rowKey={(row) => row.id}
            isLoading={statsQuery.isLoading}
            pageSize={20}
            emptyState={<EmptyState icon={Building2} title="Aucune structure" />}
          />
          <p className="mt-3 text-xs text-text-subtle">Nombre de consultations sur les six derniers mois.</p>
        </CardContent>
      </Card>
    </div>
  );
}

function StatsHeader() {
  return (
    <div>
      <h1 className="font-heading text-xl font-semibold text-text">Statistiques</h1>
      <p className="mt-1 text-sm text-text-muted">Volumes agrégés par structure — aucun accès au contenu des dossiers.</p>
    </div>
  );
}
