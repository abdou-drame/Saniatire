import { ClipboardList, FlaskConical, Send, TestTube2 } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { KpiCard } from "@/components/ui/kpi-card";
import { KpiRowSkeleton } from "@/components/ui/loading-state";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { useAuth } from "@/hooks/use-auth";
import { useLabOrders, useLabOrderStats, type LabOrderFilters } from "@/hooks/use-lab-orders";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDateTime } from "@/lib/datetime";
import { LaboratoireBiologisteSection } from "@/pages/laboratoire/laboratoire-biologiste-section";
import { LaboratoireTechnicienSection } from "@/pages/laboratoire/laboratoire-technicien-section";
import { ORDER_STATUS_BADGE, ORDER_STATUS_LABEL } from "@/pages/laboratoire/lab-status";
import type { LabOrder, LabOrderStatus } from "@/types/api";

const STATUS_OPTIONS: LabOrderStatus[] = [
  "demande",
  "prelevement_effectue",
  "en_analyse",
  "resultats_disponibles",
  "transmis",
  "annule",
];

export function LaboratoirePage() {
  const { hasRole, hasPermission } = useAuth();

  const [statusFilter, setStatusFilter] = useState<LabOrderStatus | "">("");
  const [patientIdFilter, setPatientIdFilter] = useState("");
  const [requesterIdFilter, setRequesterIdFilter] = useState("");

  const statsQuery = useLabOrderStats();

  const filters: LabOrderFilters = {
    status: statusFilter || undefined,
    patientId: patientIdFilter.trim() ? Number(patientIdFilter) : undefined,
    requesterId: requesterIdFilter.trim() ? Number(requesterIdFilter) : undefined,
  };
  const ordersQuery = useLabOrders(filters);

  const showTechnicien = hasRole("technicien_laboratoire");
  const showBiologiste = hasPermission("laboratoire.validate_biologique");

  const columns: DataTableColumn<LabOrder>[] = [
    {
      key: "patient",
      header: "Patient",
      render: (row) =>
        row.patient
          ? `${row.patient.first_name} ${row.patient.last_name} (${row.patient.patient_number})`
          : "—",
    },
    {
      key: "requester",
      header: "Prescripteur",
      render: (row) => row.requester_label ?? "—",
    },
    {
      key: "site",
      header: "Site",
      render: (row) => row.site?.name ?? "—",
    },
    {
      key: "status",
      header: "Statut",
      render: (row) => <Badge status={ORDER_STATUS_BADGE[row.status]}>{ORDER_STATUS_LABEL[row.status]}</Badge>,
    },
    {
      key: "ordered_at",
      header: "Date",
      render: (row) => formatDateTime(row.ordered_at),
    },
  ];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="font-heading text-xl font-semibold text-text">Laboratoire</h1>
        <p className="mt-1 text-sm text-text-muted">Suivi des demandes d'analyses, prélèvements et résultats.</p>
      </div>

      {statsQuery.isLoading && <KpiRowSkeleton />}
      {statsQuery.isError && (
        <ErrorState message={apiErrorMessage(statsQuery.error)} onRetry={() => statsQuery.refetch()} />
      )}
      {statsQuery.data && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard label="Demandes en attente" value={statsQuery.data.demandes_en_attente} icon={ClipboardList} />
          <KpiCard label="Prélèvements du jour" value={statsQuery.data.prelevements_du_jour} icon={TestTube2} />
          <KpiCard
            label="Résultats en attente de validation"
            value={statsQuery.data.resultats_en_attente_validation}
            icon={FlaskConical}
          />
          <KpiCard
            label="Résultats transmis aujourd'hui"
            value={statsQuery.data.resultats_transmis_aujourdhui}
            icon={Send}
          />
        </div>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Vue d'ensemble</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
              <Label>Statut</Label>
              <Select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value as LabOrderStatus | "")}
              >
                <option value="">Tous</option>
                {STATUS_OPTIONS.map((status) => (
                  <option key={status} value={status}>
                    {ORDER_STATUS_LABEL[status]}
                  </option>
                ))}
              </Select>
            </div>
            <div>
              <Label>ID patient</Label>
              <Input
                type="number"
                min={1}
                value={patientIdFilter}
                onChange={(e) => setPatientIdFilter(e.target.value)}
                placeholder="ex. 42"
              />
            </div>
            <div>
              <Label>ID praticien prescripteur</Label>
              <Input
                type="number"
                min={1}
                value={requesterIdFilter}
                onChange={(e) => setRequesterIdFilter(e.target.value)}
                placeholder="ex. 7"
              />
            </div>
          </div>

          {ordersQuery.isError ? (
            <ErrorState message={apiErrorMessage(ordersQuery.error)} onRetry={() => ordersQuery.refetch()} />
          ) : (
            <DataTable
              columns={columns}
              data={ordersQuery.data?.data ?? []}
              rowKey={(row) => row.id}
              isLoading={ordersQuery.isLoading}
              emptyState={
                <EmptyState
                  icon={FlaskConical}
                  title="Aucune demande"
                  description="Aucune demande d'analyse ne correspond à ces filtres."
                />
              }
            />
          )}
        </CardContent>
      </Card>

      {showTechnicien && <LaboratoireTechnicienSection />}
      {showBiologiste && <LaboratoireBiologisteSection />}
    </div>
  );
}
