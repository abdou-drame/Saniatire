import { useQuery } from "@tanstack/react-query";
import { Activity, CalendarClock, Clock, Users } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { KpiCard } from "@/components/ui/kpi-card";
import { KpiRowSkeleton } from "@/components/ui/loading-state";
import { useAuth } from "@/hooks/use-auth";
import { usePatientsDirectory } from "@/hooks/use-patients-directory";
import { apiErrorMessage } from "@/lib/api-error";
import { api } from "@/lib/api";
import { formatTime, isToday, minutesSince } from "@/lib/datetime";
import type { Consultation, Paginated, QueueEntry, QueuePriority } from "@/types/api";

const PRIORITY_META: Record<QueuePriority, { label: string; status: "danger" | "warning" | "neutral" }> = {
  tres_urgente: { label: "Très urgent", status: "danger" },
  urgente: { label: "Urgent", status: "warning" },
  normale: { label: "Normale", status: "neutral" },
};

const PRIORITY_ORDER: Record<QueuePriority, number> = { tres_urgente: 0, urgente: 1, normale: 2 };

export function DoctorDashboardPage() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const directory = usePatientsDirectory();

  const queueQuery = useQuery({
    queryKey: ["queue-entries", "dashboard"],
    queryFn: async () => {
      const { data } = await api.get<Paginated<QueueEntry>>("/queue-entries", {
        params: { include_exited: 1 },
      });
      return data.data;
    },
    refetchInterval: 30_000,
  });

  const openConsultationQuery = useQuery({
    queryKey: ["consultations", "en_cours", user?.id],
    queryFn: async () => {
      const { data } = await api.get<Paginated<Consultation>>("/consultations", {
        params: { practitioner_id: user!.id, status: "en_cours" },
      });
      return data.data[0] ?? null;
    },
    enabled: Boolean(user),
  });

  const completedTodayQuery = useQuery({
    queryKey: ["consultations", "terminee", user?.id],
    queryFn: async () => {
      const { data } = await api.get<Paginated<Consultation>>("/consultations", {
        params: { practitioner_id: user!.id, status: "terminee" },
      });
      return data.data;
    },
    enabled: Boolean(user),
  });

  if (queueQuery.isError) {
    return (
      <ErrorState
        message={apiErrorMessage(queueQuery.error)}
        onRetry={() => queueQuery.refetch()}
      />
    );
  }

  const myQueueToday = (queueQuery.data ?? []).filter(
    (entry) => entry.practitioner_id === user?.id && isToday(entry.arrived_at),
  );
  const waitingEntries = myQueueToday
    .filter((e) => e.status === "en_attente" || e.status === "appele")
    .sort((a, b) => {
      const priorityDiff = PRIORITY_ORDER[a.priority] - PRIORITY_ORDER[b.priority];
      if (priorityDiff !== 0) return priorityDiff;
      return new Date(a.arrived_at).getTime() - new Date(b.arrived_at).getTime();
    });

  const distinctPatientsToday = new Set(myQueueToday.map((e) => e.patient_id)).size;
  const calledEntries = myQueueToday.filter((e) => e.wait_minutes !== null);
  const avgWait =
    calledEntries.length > 0
      ? Math.round(calledEntries.reduce((sum, e) => sum + (e.wait_minutes ?? 0), 0) / calledEntries.length)
      : null;
  const completedToday = (completedTodayQuery.data ?? []).filter((c) => isToday(c.closed_at));

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
    {
      key: "priority",
      header: "Priorité",
      render: (row) => (
        <Badge status={PRIORITY_META[row.priority].status}>{PRIORITY_META[row.priority].label}</Badge>
      ),
    },
    {
      key: "wait",
      header: "Attente",
      align: "right",
      accessor: (row) => row.wait_minutes ?? minutesSince(row.arrived_at),
      render: (row) => (
        <span className="font-tabular text-text-muted">
          {row.wait_minutes ?? minutesSince(row.arrived_at)} min
        </span>
      ),
    },
    {
      key: "arrived_at",
      header: "Arrivée",
      align: "right",
      accessor: (row) => formatTime(row.arrived_at),
    },
  ];

  return (
    <div className="space-y-6">
      <Card className="glow-accent relative overflow-hidden p-6">
        <div className="relative">
          <p className="text-sm text-text-muted">Bienvenue,</p>
          <h2 className="font-heading text-xl font-semibold text-text">
            Dr {user?.last_name ?? "Utilisateur"}
          </h2>
          <p className="mt-1 text-sm text-text-muted">Voici votre activité du jour.</p>
        </div>
      </Card>

      {queueQuery.isLoading || completedTodayQuery.isLoading ? (
        <KpiRowSkeleton count={3} />
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <KpiCard label="Patients du jour" value={distinctPatientsToday} icon={Users} />
          <KpiCard
            label="Temps d'attente moyen"
            value={avgWait ?? "—"}
            unit={avgWait !== null ? "min" : undefined}
            icon={Clock}
          />
          <KpiCard label="Consultations terminées" value={completedToday.length} icon={Activity} />
        </div>
      )}

      {openConsultationQuery.data && (
        <Card className="border-accent/30 bg-accent/5 p-5">
          <div className="flex items-center justify-between gap-4">
            <div>
              <p className="text-xs font-medium uppercase tracking-wide text-accent-light">
                Consultation en cours
              </p>
              <p className="mt-1 text-sm font-medium text-text">
                {(() => {
                  const patient = directory.byId.get(openConsultationQuery.data!.patient_id);
                  return patient
                    ? `${patient.first_name} ${patient.last_name}`
                    : `Patient #${openConsultationQuery.data!.patient_id}`;
                })()}
              </p>
              <p className="text-xs text-text-muted">{openConsultationQuery.data.reason}</p>
            </div>
            <Button onClick={() => navigate(`/patients/${openConsultationQuery.data!.patient_id}`)}>
              Reprendre
            </Button>
          </div>
        </Card>
      )}

      <Card>
        <CardHeader>
          <CardTitle>File d'attente</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable
            columns={columns}
            data={waitingEntries}
            rowKey={(row) => row.id}
            isLoading={queueQuery.isLoading || directory.isLoading}
            onRowClick={(row) => navigate(`/patients/${row.patient_id}`, { state: { queueEntry: row } })}
            emptyState={
              <EmptyState
                icon={CalendarClock}
                title="Aucun patient en attente"
                description="Les patients qui vous sont assignés dans la file d'attente apparaîtront ici."
              />
            }
          />
        </CardContent>
      </Card>
    </div>
  );
}
