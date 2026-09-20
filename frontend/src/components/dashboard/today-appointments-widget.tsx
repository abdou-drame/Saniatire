import { CalendarClock } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { useAppointments } from "@/hooks/use-appointments";
import { usePatientsDirectory } from "@/hooks/use-patients-directory";
import { usePractitioners } from "@/hooks/use-practitioners";
import { apiErrorMessage } from "@/lib/api-error";
import { formatTime } from "@/lib/datetime";
import { todayKey } from "@/lib/date-range";
import type { Appointment, AppointmentStatus } from "@/types/api";

const STATUS_META: Record<AppointmentStatus, { label: string; status: "neutral" | "accent" | "accent2" | "success" | "danger" | "warning" }> = {
  planifie: { label: "Planifié", status: "neutral" },
  confirme: { label: "Confirmé", status: "accent" },
  en_cours: { label: "En cours", status: "accent2" },
  termine: { label: "Terminé", status: "success" },
  annule: { label: "Annulé", status: "danger" },
  absent: { label: "Absent", status: "warning" },
};

export function TodayAppointmentsWidget() {
  const navigate = useNavigate();
  const today = todayKey();
  const query = useAppointments({ from: today, to: today });
  const patientsDirectory = usePatientsDirectory();
  const practitionersQuery = usePractitioners();

  function patientLabel(patientId: number): string {
    const patient = patientsDirectory.byId.get(patientId);
    return patient ? `${patient.first_name} ${patient.last_name}` : `Patient #${patientId}`;
  }

  function practitionerLabel(practitionerId: number): string {
    const practitioner = practitionersQuery.data?.find((p) => p.id === practitionerId);
    return practitioner ? `${practitioner.first_name} ${practitioner.last_name}` : `Praticien #${practitionerId}`;
  }

  const columns: DataTableColumn<Appointment>[] = [
    { key: "starts_at", header: "Heure", accessor: (row) => formatTime(row.starts_at) },
    { key: "patient", header: "Patient", accessor: (row) => patientLabel(row.patient_id) },
    { key: "practitioner", header: "Praticien", accessor: (row) => practitionerLabel(row.practitioner_id) },
    {
      key: "status",
      header: "Statut",
      render: (row) => <Badge status={STATUS_META[row.status].status}>{STATUS_META[row.status].label}</Badge>,
    },
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Rendez-vous du jour</CardTitle>
        <Button variant="ghost" size="sm" onClick={() => navigate("/rendez-vous")}>
          Voir l'agenda
        </Button>
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
            emptyState={
              <EmptyState icon={CalendarClock} title="Aucun rendez-vous aujourd'hui" description="Le planning du jour est vide." />
            }
          />
        )}
      </CardContent>
    </Card>
  );
}
