import { Archive, Building2, CheckCircle2, PauseCircle, Plus } from "lucide-react";
import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { CreateStructureDialog } from "@/components/platform/create-structure-dialog";
import {
  PlatformInitials,
  PlatformNote,
  PlatformPageHeader,
  PlatformStat,
  PlatformStatSkeleton,
} from "@/components/platform/platform-ui";
import { structureTypeLabel } from "@/components/platform/structure-labels";
import { StructureStatusBadge } from "@/components/platform/structure-status-badge";
import { usePlatformStructures } from "@/hooks/use-platform-structures";
import { apiErrorMessage } from "@/lib/api-error";
import { formatNumber } from "@/lib/format";
import type { Structure } from "@/types/api";

export function PlatformStructuresPage() {
  const { data: structures, isLoading, isError, error, refetch } = usePlatformStructures();
  const [createOpen, setCreateOpen] = useState(false);
  const navigate = useNavigate();

  const list = structures ?? [];
  const counts = {
    total: list.length,
    active: list.filter((s) => !s.archived_at && s.is_active).length,
    suspended: list.filter((s) => !s.archived_at && !s.is_active).length,
    archived: list.filter((s) => Boolean(s.archived_at)).length,
  };

  const columns: DataTableColumn<Structure>[] = [
    {
      key: "legal_name",
      header: "Nom",
      sortable: true,
      accessor: (s) => s.legal_name,
      render: (s) => (
        <div className="flex min-w-[12rem] items-center gap-3">
          <PlatformInitials name={s.legal_name} className="h-8 w-8" />
          <div className="min-w-0">
            <p className="font-medium text-text">{s.legal_name}</p>
            {s.trade_name && s.trade_name !== s.legal_name && (
              <p className="truncate text-xs text-text-muted">{s.trade_name}</p>
            )}
          </div>
        </div>
      ),
    },
    {
      key: "code",
      header: "Code",
      sortable: true,
      accessor: (s) => s.code,
      render: (s) => <span className="font-mono text-xs text-text-muted">{s.code}</span>,
    },
    {
      key: "type",
      header: "Type",
      accessor: (s) => structureTypeLabel(s.type),
      render: (s) => <span className="whitespace-nowrap">{structureTypeLabel(s.type)}</span>,
    },
    { key: "city", header: "Ville", accessor: (s) => s.city ?? "—" },
    { key: "is_active", header: "Statut", render: (s) => <StructureStatusBadge structure={s} /> },
  ];

  return (
    <div className="space-y-6">
      <PlatformPageHeader
        icon={Building2}
        title="Structures"
        description="Structures clientes de la plateforme : création, statut, abonnement, modules et accès."
        actions={
          <Button onClick={() => setCreateOpen(true)}>
            <Plus size={16} />
            Nouvelle structure
          </Button>
        }
      />

      {isLoading ? (
        <PlatformStatSkeleton count={4} />
      ) : !isError ? (
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <PlatformStat label="Total" value={formatNumber(counts.total)} icon={Building2} tone="accent" />
          <PlatformStat label="Actives" value={formatNumber(counts.active)} icon={CheckCircle2} tone="success" />
          <PlatformStat label="Suspendues" value={formatNumber(counts.suspended)} icon={PauseCircle} tone="warning" />
          <PlatformStat label="Archivées" value={formatNumber(counts.archived)} icon={Archive} tone="danger" />
        </div>
      ) : null}

      <Card className="p-4 sm:p-5">
        <div className="mb-4 flex flex-wrap items-baseline justify-between gap-2">
          <h2 className="font-heading text-sm font-semibold text-text">Toutes les structures</h2>
          {list.length > 0 && <PlatformNote>Cliquez sur une ligne pour ouvrir la fiche de la structure.</PlatformNote>}
        </div>
        {isError ? (
          <ErrorState message={apiErrorMessage(error)} onRetry={() => refetch()} />
        ) : (
          <DataTable
            columns={columns}
            data={list}
            rowKey={(s) => s.id}
            isLoading={isLoading}
            onRowClick={(s) => navigate(`/platform/structures/${s.id}`)}
            emptyState={
              <EmptyState
                icon={Building2}
                title="Aucune structure"
                description="Créez la première structure cliente de la plateforme."
                actionLabel="Nouvelle structure"
                onAction={() => setCreateOpen(true)}
              />
            }
          />
        )}
      </Card>

      <CreateStructureDialog open={createOpen} onOpenChange={setCreateOpen} />
    </div>
  );
}
