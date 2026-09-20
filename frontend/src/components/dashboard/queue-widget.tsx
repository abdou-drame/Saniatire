import { useQuery } from "@tanstack/react-query";
import { Users } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { usePatientsDirectory } from "@/hooks/use-patients-directory";
import { apiErrorMessage } from "@/lib/api-error";
import { api } from "@/lib/api";
import { minutesSince } from "@/lib/datetime";
import type { Paginated, QueueEntry, QueuePriority } from "@/types/api";

const PRIORITY_META: Record<QueuePriority, { label: string; status: "danger" | "warning" | "neutral" }> = {
  tres_urgente: { label: "Très urgent", status: "danger" },
  urgente: { label: "Urgent", status: "warning" },
  normale: { label: "Normale", status: "neutral" },
};

/** Sans site_id, /queue-entries renvoie les entrées actives de tous les sites de la structure. */
export function QueueWidget() {
  const navigate = useNavigate();
  const directory = usePatientsDirectory();
  const query = useQuery({
    queryKey: ["queue-entries", "dashboard-all-sites"],
    queryFn: async () => {
      const { data } = await api.get<Paginated<QueueEntry>>("/queue-entries");
      return data.data;
    },
    refetchInterval: 30_000,
  });

  function patientLabel(patientId: number): string {
    const patient = directory.byId.get(patientId);
    return patient ? `${patient.first_name} ${patient.last_name}` : `Patient #${patientId}`;
  }

  const columns: DataTableColumn<QueueEntry>[] = [
    { key: "patient", header: "Patient", accessor: (row) => patientLabel(row.patient_id) },
    { key: "service", header: "Service", accessor: (row) => row.service },
    {
      key: "priority",
      header: "Priorité",
      render: (row) => <Badge status={PRIORITY_META[row.priority].status}>{PRIORITY_META[row.priority].label}</Badge>,
    },
    {
      key: "wait",
      header: "Attente",
      align: "right",
      accessor: (row) => row.wait_minutes ?? minutesSince(row.arrived_at),
      render: (row) => <span className="text-text-muted">{row.wait_minutes ?? minutesSince(row.arrived_at)} min</span>,
    },
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>File d'attente</CardTitle>
      </CardHeader>
      <CardContent>
        {query.isError ? (
          <ErrorState message={apiErrorMessage(query.error)} onRetry={() => query.refetch()} />
        ) : (
          <DataTable
            columns={columns}
            data={query.data ?? []}
            rowKey={(row) => row.id}
            isLoading={query.isLoading}
            onRowClick={(row) => navigate(`/patients/${row.patient_id}`)}
            emptyState={<EmptyState icon={Users} title="Aucun patient en attente" />}
          />
        )}
      </CardContent>
    </Card>
  );
}
