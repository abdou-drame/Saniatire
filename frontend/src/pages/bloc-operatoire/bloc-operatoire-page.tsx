import { CalendarPlus, Syringe } from "lucide-react";
import { useState } from "react";
import { PlanSurgicalProcedureDialog } from "@/components/bloc-operatoire/plan-surgical-procedure-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { useAuth } from "@/hooks/use-auth";
import { useSurgicalProcedures, type SurgicalProcedureFilters } from "@/hooks/use-surgical-procedures";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDateTime } from "@/lib/datetime";
import { PROCEDURE_STATUS_BADGE, PROCEDURE_STATUS_LABEL } from "@/pages/bloc-operatoire/bloc-operatoire-status";
import { SurgicalProcedureDetail } from "@/pages/bloc-operatoire/surgical-procedure-detail";
import type { SurgicalProcedure, SurgicalProcedureStatus } from "@/types/api";

const STATUS_OPTIONS: SurgicalProcedureStatus[] = ["planifiee", "en_cours", "terminee", "annulee"];

export function BlocOperatoirePage() {
  const { user, hasPermission } = useAuth();

  const [statusFilter, setStatusFilter] = useState<SurgicalProcedureStatus | "">("");
  const [patientIdFilter, setPatientIdFilter] = useState("");
  const [planDialogOpen, setPlanDialogOpen] = useState(false);
  const [selectedId, setSelectedId] = useState<number | null>(null);

  const filters: SurgicalProcedureFilters = {
    status: statusFilter || undefined,
    patientId: patientIdFilter.trim() ? Number(patientIdFilter) : undefined,
  };
  const proceduresQuery = useSurgicalProcedures(filters);

  const canPlan = hasPermission("bloc_operatoire.create");

  const columns: DataTableColumn<SurgicalProcedure>[] = [
    {
      key: "patient",
      header: "Patient",
      render: (row) =>
        row.patient ? `${row.patient.first_name} ${row.patient.last_name} (${row.patient.patient_number})` : "—",
    },
    {
      key: "procedure_type",
      header: "Type d'intervention",
      accessor: (row) => row.procedure_type,
    },
    {
      key: "surgeon",
      header: "Chirurgien",
      render: (row) => row.surgeon_label ?? "—",
    },
    {
      key: "anesthesiologist",
      header: "Anesthésiste",
      render: (row) => row.anesthesiologist_label ?? "—",
    },
    {
      key: "operating_room",
      header: "Salle",
      accessor: (row) => row.operating_room,
    },
    {
      key: "status",
      header: "Statut",
      render: (row) => <Badge status={PROCEDURE_STATUS_BADGE[row.status]}>{PROCEDURE_STATUS_LABEL[row.status]}</Badge>,
    },
    {
      key: "scheduled_at",
      header: "Date planifiée",
      render: (row) => formatDateTime(row.scheduled_at),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="font-heading text-xl font-semibold text-text">Bloc opératoire</h1>
          <p className="mt-1 text-sm text-text-muted">Planification des interventions et checklist chirurgicale.</p>
        </div>
        {canPlan && (
          <Button onClick={() => setPlanDialogOpen(true)}>
            <CalendarPlus size={14} />
            Planifier une intervention
          </Button>
        )}
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Interventions</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Statut</Label>
              <Select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value as SurgicalProcedureStatus | "")}
              >
                <option value="">Tous</option>
                {STATUS_OPTIONS.map((status) => (
                  <option key={status} value={status}>
                    {PROCEDURE_STATUS_LABEL[status]}
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
          </div>

          {proceduresQuery.isError ? (
            <ErrorState message={apiErrorMessage(proceduresQuery.error)} onRetry={() => proceduresQuery.refetch()} />
          ) : (
            <DataTable
              columns={columns}
              data={proceduresQuery.data?.data ?? []}
              rowKey={(row) => row.id}
              isLoading={proceduresQuery.isLoading}
              onRowClick={(row) => setSelectedId(row.id)}
              emptyState={
                <EmptyState
                  icon={Syringe}
                  title="Aucune intervention"
                  description="Aucune intervention ne correspond à ces filtres."
                />
              }
            />
          )}
        </CardContent>
      </Card>

      {selectedId && <SurgicalProcedureDetail procedureId={selectedId} onClose={() => setSelectedId(null)} />}

      {canPlan && user && (
        <PlanSurgicalProcedureDialog
          open={planDialogOpen}
          onOpenChange={setPlanDialogOpen}
          siteId={user.sites[0]?.id ?? null}
          onPlanned={() => proceduresQuery.refetch()}
        />
      )}
    </div>
  );
}
