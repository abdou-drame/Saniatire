import { CalendarCheck, ClipboardList, FileCheck2, Scan, Send } from "lucide-react";
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
import { useImagingOrders, useImagingOrderStats, type ImagingOrderFilters } from "@/hooks/use-imaging-orders";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDateTime } from "@/lib/datetime";
import { ImagerieManipulateurSection } from "@/pages/imagerie/imagerie-manipulateur-section";
import { ImagerieRadiologueSection } from "@/pages/imagerie/imagerie-radiologue-section";
import { EXAM_TYPE_LABEL, ORDER_STATUS_BADGE, ORDER_STATUS_LABEL } from "@/pages/imagerie/imaging-status";
import type { ImagingExamType, ImagingOrder, ImagingOrderStatus } from "@/types/api";

const STATUS_OPTIONS: ImagingOrderStatus[] = [
  "demande",
  "planifie",
  "realise",
  "en_interpretation",
  "cr_redige",
  "valide",
  "transmis",
  "annule",
];

const EXAM_TYPE_OPTIONS: ImagingExamType[] = ["radio", "echo", "scanner", "irm"];

export function ImageriePage() {
  const { hasPermission } = useAuth();

  const [statusFilter, setStatusFilter] = useState<ImagingOrderStatus | "">("");
  const [examTypeFilter, setExamTypeFilter] = useState<ImagingExamType | "">("");
  const [patientIdFilter, setPatientIdFilter] = useState("");
  const [requesterIdFilter, setRequesterIdFilter] = useState("");

  const statsQuery = useImagingOrderStats();

  const filters: ImagingOrderFilters = {
    status: statusFilter || undefined,
    examType: examTypeFilter || undefined,
    patientId: patientIdFilter.trim() ? Number(patientIdFilter) : undefined,
    requesterId: requesterIdFilter.trim() ? Number(requesterIdFilter) : undefined,
  };
  const ordersQuery = useImagingOrders(filters);

  const showManipulateur = hasPermission("imagerie.create");
  const showRadiologue = hasPermission("imagerie.interpreter") || hasPermission("imagerie.validate");

  const columns: DataTableColumn<ImagingOrder>[] = [
    {
      key: "patient",
      header: "Patient",
      render: (row) =>
        row.patient
          ? `${row.patient.first_name} ${row.patient.last_name} (${row.patient.patient_number})`
          : "—",
    },
    {
      key: "exam_type",
      header: "Type d'examen",
      render: (row) => EXAM_TYPE_LABEL[row.exam_type],
    },
    {
      key: "requester",
      header: "Prescripteur",
      render: (row) => row.requester_label ?? "—",
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
        <h1 className="font-heading text-xl font-semibold text-text">Imagerie</h1>
        <p className="mt-1 text-sm text-text-muted">Suivi des demandes d'examens, réalisations et comptes rendus.</p>
      </div>

      {statsQuery.isLoading && <KpiRowSkeleton />}
      {statsQuery.isError && (
        <ErrorState message={apiErrorMessage(statsQuery.error)} onRetry={() => statsQuery.refetch()} />
      )}
      {statsQuery.data && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard label="Examens en attente" value={statsQuery.data.examens_en_attente} icon={ClipboardList} />
          <KpiCard label="Réalisés aujourd'hui" value={statsQuery.data.realises_aujourdhui} icon={CalendarCheck} />
          <KpiCard
            label="Comptes rendus en attente de validation"
            value={statsQuery.data.comptes_rendus_en_attente_validation}
            icon={FileCheck2}
          />
          <KpiCard label="Transmis aujourd'hui" value={statsQuery.data.transmis_aujourdhui} icon={Send} />
        </div>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Vue d'ensemble</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <div>
              <Label>Type d'examen</Label>
              <Select
                value={examTypeFilter}
                onChange={(e) => setExamTypeFilter(e.target.value as ImagingExamType | "")}
              >
                <option value="">Tous</option>
                {EXAM_TYPE_OPTIONS.map((type) => (
                  <option key={type} value={type}>
                    {EXAM_TYPE_LABEL[type]}
                  </option>
                ))}
              </Select>
            </div>
            <div>
              <Label>Statut</Label>
              <Select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value as ImagingOrderStatus | "")}
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
                  icon={Scan}
                  title="Aucune demande"
                  description="Aucune demande d'imagerie ne correspond à ces filtres."
                />
              }
            />
          )}
        </CardContent>
      </Card>

      {showManipulateur && <ImagerieManipulateurSection />}
      {showRadiologue && <ImagerieRadiologueSection />}
    </div>
  );
}
