import { AlertTriangle, CalendarClock, PlusCircle, Wrench, X } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Label } from "@/components/ui/label";
import { KpiCard } from "@/components/ui/kpi-card";
import { KpiRowSkeleton, Skeleton } from "@/components/ui/loading-state";
import { Select } from "@/components/ui/select";
import { CreateEquipmentDialog } from "@/components/equipements/create-equipment-dialog";
import { RecordMaintenanceDialog } from "@/components/equipements/record-maintenance-dialog";
import { useAuth } from "@/hooks/use-auth";
import {
  useEquipment,
  useEquipments,
  useOverdueMaintenances,
  useUpcomingMaintenances,
} from "@/hooks/use-biomedical-equipment";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import {
  EQUIPMENT_STATUS_BADGE,
  EQUIPMENT_STATUS_LABEL,
  MAINTENANCE_STATUS_BADGE,
  MAINTENANCE_STATUS_LABEL,
  MAINTENANCE_TYPE_LABEL,
} from "@/pages/equipements/equipements-status";
import type { BiomedicalEquipment, BiomedicalEquipmentStatut, EquipmentMaintenance } from "@/types/api";

const STATUS_OPTIONS: BiomedicalEquipmentStatut[] = ["en_service", "en_maintenance", "hors_service", "reforme"];

function formatDateOrDash(isoDate: string | null): string {
  return isoDate ? formatDate(isoDate) : "—";
}

function formatCost(cout: number | null): string {
  return cout === null ? "—" : `${cout.toLocaleString("fr-FR")} FCFA`;
}

export function EquipementsPage() {
  const { hasPermission } = useAuth();
  const canCreateEquipment = hasPermission("biomedical.create");
  const canRecordMaintenance = hasPermission("biomedical.maintenance");

  const [statusFilter, setStatusFilter] = useState<BiomedicalEquipmentStatut | "">("");
  const [selectedEquipmentId, setSelectedEquipmentId] = useState<number | null>(null);
  const [createOpen, setCreateOpen] = useState(false);
  const [maintenanceOpen, setMaintenanceOpen] = useState(false);

  const upcomingQuery = useUpcomingMaintenances();
  const overdueQuery = useOverdueMaintenances();
  const equipmentsQuery = useEquipments({ statut: statusFilter || undefined });
  const selectedEquipmentQuery = useEquipment(selectedEquipmentId ?? undefined);

  const equipmentColumns: DataTableColumn<BiomedicalEquipment>[] = [
    { key: "nom", header: "Nom", render: (row) => row.nom },
    { key: "categorie", header: "Catégorie", render: (row) => row.categorie },
    { key: "numero_serie", header: "N° de série", render: (row) => row.numero_serie },
    { key: "site", header: "Site", render: (row) => row.site?.name ?? "—" },
    {
      key: "statut",
      header: "Statut",
      render: (row) => <Badge status={EQUIPMENT_STATUS_BADGE[row.statut]}>{EQUIPMENT_STATUS_LABEL[row.statut]}</Badge>,
    },
    { key: "supplier", header: "Fournisseur", render: (row) => row.supplier?.nom ?? "—" },
  ];

  const detailMaintenanceColumns: DataTableColumn<EquipmentMaintenance>[] = [
    { key: "type", header: "Type", render: (row) => MAINTENANCE_TYPE_LABEL[row.type] },
    { key: "date_prevue", header: "Date prévue", render: (row) => formatDate(row.date_prevue) },
    { key: "date_realisee", header: "Date réalisée", render: (row) => formatDateOrDash(row.date_realisee) },
    {
      key: "statut",
      header: "Statut",
      render: (row) => (
        <Badge status={MAINTENANCE_STATUS_BADGE[row.statut]}>{MAINTENANCE_STATUS_LABEL[row.statut]}</Badge>
      ),
    },
    { key: "intervenant", header: "Intervenant", render: (row) => row.intervenant_label ?? row.intervenant_externe ?? "—" },
    { key: "cout", header: "Coût", render: (row) => formatCost(row.cout) },
  ];

  function upcomingOverdueColumns(highlight: boolean): DataTableColumn<EquipmentMaintenance>[] {
    return [
      { key: "equipment", header: "Équipement", render: (row) => row.equipment?.nom ?? "—" },
      {
        key: "date_prevue",
        header: "Date prévue",
        render: (row) => (
          <span className={highlight ? "font-medium text-danger" : undefined}>{formatDate(row.date_prevue)}</span>
        ),
      },
      { key: "type", header: "Type", render: (row) => MAINTENANCE_TYPE_LABEL[row.type] },
      {
        key: "intervenant",
        header: "Intervenant",
        render: (row) => row.intervenant_label ?? row.intervenant_externe ?? "—",
      },
      ...(highlight
        ? [
            {
              key: "alert",
              header: "",
              render: () => (
                <Badge status="danger" dot={false}>
                  En retard
                </Badge>
              ),
            } as DataTableColumn<EquipmentMaintenance>,
          ]
        : []),
    ];
  }

  const selectedEquipment = selectedEquipmentQuery.data;

  return (
    <div className="space-y-6">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h1 className="font-heading text-xl font-semibold text-text">Équipements biomédicaux</h1>
          <p className="mt-1 text-sm text-text-muted">
            Inventaire des équipements et suivi des interventions de maintenance préventive et corrective.
          </p>
        </div>
        {canCreateEquipment && (
          <Button onClick={() => setCreateOpen(true)}>
            <PlusCircle size={16} />
            Nouvel équipement
          </Button>
        )}
      </div>

      {upcomingQuery.isLoading || overdueQuery.isLoading ? (
        <KpiRowSkeleton count={2} />
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          {upcomingQuery.isError ? (
            <ErrorState message={apiErrorMessage(upcomingQuery.error)} onRetry={() => upcomingQuery.refetch()} />
          ) : (
            <KpiCard label="Maintenances à venir" value={upcomingQuery.data?.length ?? 0} icon={CalendarClock} />
          )}
          {overdueQuery.isError ? (
            <ErrorState message={apiErrorMessage(overdueQuery.error)} onRetry={() => overdueQuery.refetch()} />
          ) : (
            <KpiCard
              label="Maintenances en retard"
              value={overdueQuery.data?.length ?? 0}
              icon={AlertTriangle}
              className={(overdueQuery.data?.length ?? 0) > 0 ? "border-danger/40 bg-danger/5" : undefined}
            />
          )}
        </div>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Inventaire</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
              <Label>Statut</Label>
              <Select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value as BiomedicalEquipmentStatut | "")}
              >
                <option value="">Tous</option>
                {STATUS_OPTIONS.map((status) => (
                  <option key={status} value={status}>
                    {EQUIPMENT_STATUS_LABEL[status]}
                  </option>
                ))}
              </Select>
            </div>
          </div>

          {equipmentsQuery.isError ? (
            <ErrorState message={apiErrorMessage(equipmentsQuery.error)} onRetry={() => equipmentsQuery.refetch()} />
          ) : (
            <DataTable
              columns={equipmentColumns}
              data={equipmentsQuery.data?.data ?? []}
              rowKey={(row) => row.id}
              isLoading={equipmentsQuery.isLoading}
              onRowClick={(row) => setSelectedEquipmentId(row.id)}
              emptyState={
                <EmptyState
                  icon={Wrench}
                  title="Aucun équipement"
                  description="Aucun équipement biomédical ne correspond à ce filtre."
                />
              }
            />
          )}
        </CardContent>
      </Card>

      {selectedEquipmentId !== null && (
        <Card>
          <CardHeader>
            <CardTitle>Détail de l'équipement</CardTitle>
            <div className="flex items-center gap-2">
              {canRecordMaintenance && selectedEquipment && (
                <Button size="sm" onClick={() => setMaintenanceOpen(true)}>
                  <Wrench size={14} />
                  Enregistrer une maintenance
                </Button>
              )}
              <Button variant="ghost" size="icon" onClick={() => setSelectedEquipmentId(null)} aria-label="Fermer">
                <X size={16} />
              </Button>
            </div>
          </CardHeader>
          <CardContent className="space-y-5">
            {selectedEquipmentQuery.isLoading && (
              <div className="space-y-2">
                <Skeleton className="h-4 w-1/3" />
                <Skeleton className="h-4 w-1/2" />
                <Skeleton className="h-4 w-1/4" />
              </div>
            )}
            {selectedEquipmentQuery.isError && (
              <ErrorState
                message={apiErrorMessage(selectedEquipmentQuery.error)}
                onRetry={() => selectedEquipmentQuery.refetch()}
              />
            )}
            {selectedEquipment && (
              <>
                <div className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                  <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Nom</p>
                    <p className="mt-0.5 text-text">{selectedEquipment.nom}</p>
                  </div>
                  <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Catégorie</p>
                    <p className="mt-0.5 text-text">{selectedEquipment.categorie}</p>
                  </div>
                  <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">N° de série</p>
                    <p className="mt-0.5 text-text">{selectedEquipment.numero_serie}</p>
                  </div>
                  <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Site</p>
                    <p className="mt-0.5 text-text">{selectedEquipment.site?.name ?? "—"}</p>
                  </div>
                  <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Fournisseur</p>
                    <p className="mt-0.5 text-text">{selectedEquipment.supplier?.nom ?? "—"}</p>
                  </div>
                  <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Statut</p>
                    <p className="mt-0.5">
                      <Badge status={EQUIPMENT_STATUS_BADGE[selectedEquipment.statut]}>
                        {EQUIPMENT_STATUS_LABEL[selectedEquipment.statut]}
                      </Badge>
                    </p>
                  </div>
                  <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Date d'acquisition</p>
                    <p className="mt-0.5 text-text">
                      {selectedEquipment.date_acquisition ? formatDate(selectedEquipment.date_acquisition) : "—"}
                    </p>
                  </div>
                  <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Fin de garantie</p>
                    <p className="mt-0.5 text-text">{formatDateOrDash(selectedEquipment.date_fin_garantie)}</p>
                  </div>
                </div>

                <div>
                  <h3 className="mb-2 text-sm font-medium text-text">Historique de maintenance</h3>
                  <DataTable
                    columns={detailMaintenanceColumns}
                    data={selectedEquipment.maintenances ?? []}
                    rowKey={(row) => row.id}
                    emptyState={
                      <EmptyState
                        icon={Wrench}
                        title="Aucune maintenance"
                        description="Aucune intervention n'a été enregistrée pour cet équipement."
                      />
                    }
                  />
                </div>
              </>
            )}
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Maintenances à venir</CardTitle>
        </CardHeader>
        <CardContent>
          {upcomingQuery.isError ? (
            <ErrorState message={apiErrorMessage(upcomingQuery.error)} onRetry={() => upcomingQuery.refetch()} />
          ) : (
            <DataTable
              columns={upcomingOverdueColumns(false)}
              data={upcomingQuery.data ?? []}
              rowKey={(row) => row.id}
              isLoading={upcomingQuery.isLoading}
              emptyState={
                <EmptyState
                  icon={CalendarClock}
                  title="Aucune maintenance à venir"
                  description="Aucune intervention n'est planifiée prochainement."
                />
              }
            />
          )}
        </CardContent>
      </Card>

      <Card className="border-danger/30">
        <CardHeader>
          <CardTitle className="flex items-center gap-1.5 text-danger">
            <AlertTriangle size={15} />
            Maintenances en retard
          </CardTitle>
        </CardHeader>
        <CardContent>
          {overdueQuery.isError ? (
            <ErrorState message={apiErrorMessage(overdueQuery.error)} onRetry={() => overdueQuery.refetch()} />
          ) : (
            <DataTable
              columns={upcomingOverdueColumns(true)}
              data={overdueQuery.data ?? []}
              rowKey={(row) => row.id}
              isLoading={overdueQuery.isLoading}
              emptyState={
                <EmptyState
                  icon={AlertTriangle}
                  title="Aucune maintenance en retard"
                  description="Toutes les interventions planifiées sont à jour."
                />
              }
            />
          )}
        </CardContent>
      </Card>

      <CreateEquipmentDialog open={createOpen} onOpenChange={setCreateOpen} />

      {selectedEquipmentId !== null && (
        <RecordMaintenanceDialog
          open={maintenanceOpen}
          onOpenChange={setMaintenanceOpen}
          biomedicalEquipmentId={selectedEquipmentId}
          equipmentName={selectedEquipment?.nom}
        />
      )}
    </div>
  );
}
