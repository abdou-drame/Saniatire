import { UserPlus, Users } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { usePatientsDirectory } from "@/hooks/use-patients-directory";
import { usePractitioners } from "@/hooks/use-practitioners";
import { useQueueEntries, useUpdateQueueStatus } from "@/hooks/use-queue-entries";
import { apiErrorMessage } from "@/lib/api-error";
import { formatTime, isToday, minutesSince } from "@/lib/datetime";
import type { QueueEntry, QueuePriority, QueueStatus } from "@/types/api";
import { CheckInDialog } from "@/pages/reception/check-in-dialog";

const PRIORITY_META: Record<QueuePriority, { label: string; status: "danger" | "warning" | "neutral" }> = {
  tres_urgente: { label: "Très urgent", status: "danger" },
  urgente: { label: "Urgent", status: "warning" },
  normale: { label: "Normale", status: "neutral" },
};

const STATUS_META: Record<QueueStatus, { label: string; status: "warning" | "accent" | "accent2" | "neutral" }> = {
  en_attente: { label: "En attente", status: "warning" },
  appele: { label: "Appelé", status: "accent" },
  en_consultation: { label: "En consultation", status: "accent2" },
  sorti: { label: "Sorti", status: "neutral" },
};

const NEXT_STATUS: Partial<Record<QueueStatus, { next: QueueStatus; label: string }>> = {
  en_attente: { next: "appele", label: "Appeler" },
  appele: { next: "en_consultation", label: "Démarrer" },
  en_consultation: { next: "sorti", label: "Terminer" },
};

const PRIORITY_ORDER: Record<QueuePriority, number> = { tres_urgente: 0, urgente: 1, normale: 2 };

export interface QueuePanelProps {
  siteId: number;
}

export function QueuePanel({ siteId }: QueuePanelProps) {
  const [checkInOpen, setCheckInOpen] = useState(false);
  const directory = usePatientsDirectory();
  const practitioners = usePractitioners();
  const queueQuery = useQueueEntries(siteId);
  const updateStatus = useUpdateQueueStatus();

  if (queueQuery.isError) {
    return <ErrorState message={apiErrorMessage(queueQuery.error)} onRetry={() => queueQuery.refetch()} />;
  }

  const todaysQueue = (queueQuery.data ?? []).filter((entry) => isToday(entry.arrived_at));
  const sorted = [...todaysQueue].sort((a, b) => {
    const priorityDiff = PRIORITY_ORDER[a.priority] - PRIORITY_ORDER[b.priority];
    if (priorityDiff !== 0) return priorityDiff;
    return new Date(a.arrived_at).getTime() - new Date(b.arrived_at).getTime();
  });

  function practitionerName(id: number | null): string {
    if (!id) return "—";
    const p = practitioners.data?.find((p) => p.id === id);
    return p ? `Dr ${p.first_name} ${p.last_name}` : `#${id}`;
  }

  const columns: DataTableColumn<QueueEntry>[] = [
    {
      key: "patient",
      header: "Patient",
      sortable: true,
      accessor: (row) => {
        const patient = directory.byId.get(row.patient_id);
        return patient ? `${patient.first_name} ${patient.last_name}` : `Patient #${row.patient_id}`;
      },
    },
    { key: "service", header: "Service", sortable: true, accessor: (row) => row.service },
    { key: "practitioner", header: "Praticien", accessor: (row) => practitionerName(row.practitioner_id) },
    {
      key: "priority",
      header: "Priorité",
      render: (row) => <Badge status={PRIORITY_META[row.priority].status}>{PRIORITY_META[row.priority].label}</Badge>,
    },
    {
      key: "status",
      header: "Statut",
      render: (row) => <Badge status={STATUS_META[row.status].status}>{STATUS_META[row.status].label}</Badge>,
    },
    {
      key: "wait",
      header: "Attente",
      align: "right",
      accessor: (row) => row.wait_minutes ?? minutesSince(row.arrived_at),
      render: (row) => (
        <span className="font-tabular text-text-muted">{row.wait_minutes ?? minutesSince(row.arrived_at)} min</span>
      ),
    },
    { key: "arrived_at", header: "Arrivée", align: "right", accessor: (row) => formatTime(row.arrived_at) },
    {
      key: "actions",
      header: "",
      align: "right",
      render: (row) => {
        const next = NEXT_STATUS[row.status];
        if (!next) return null;
        return (
          <Button
            size="sm"
            variant="secondary"
            disabled={updateStatus.isPending}
            onClick={(e) => {
              e.stopPropagation();
              updateStatus.mutate({ id: row.id, status: next.next });
            }}
          >
            {next.label}
          </Button>
        );
      },
    },
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>File d'attente du jour</CardTitle>
        <Button size="sm" onClick={() => setCheckInOpen(true)}>
          <UserPlus size={14} />
          Enregistrer l'arrivée
        </Button>
      </CardHeader>
      <CardContent>
        <DataTable
          columns={columns}
          data={sorted}
          rowKey={(row) => row.id}
          isLoading={queueQuery.isLoading || directory.isLoading}
          emptyState={
            <EmptyState
              icon={Users}
              title="Aucun patient dans la file"
              description="Aucune arrivée enregistrée aujourd'hui pour ce site."
              actionLabel="Enregistrer l'arrivée"
              onAction={() => setCheckInOpen(true)}
            />
          }
        />
      </CardContent>

      <CheckInDialog open={checkInOpen} onOpenChange={setCheckInOpen} siteId={siteId} todaysQueue={todaysQueue} />
    </Card>
  );
}
