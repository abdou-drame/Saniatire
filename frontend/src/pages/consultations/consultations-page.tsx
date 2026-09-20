import { Stethoscope } from "lucide-react";
import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Select } from "@/components/ui/select";
import { useConsultations } from "@/hooks/use-consultations";
import { usePatientsDirectory } from "@/hooks/use-patients-directory";
import { usePractitioners } from "@/hooks/use-practitioners";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDateTime } from "@/lib/datetime";
import type { Consultation, ConsultationStatus } from "@/types/api";

const STATUS_LABEL: Record<ConsultationStatus, string> = {
  en_cours: "En cours",
  terminee: "Terminée",
};

const STATUS_VALUES: ConsultationStatus[] = ["en_cours", "terminee"];

export function ConsultationsPage() {
  const navigate = useNavigate();
  const [statutFilter, setStatutFilter] = useState<ConsultationStatus | "">("");

  const consultationsQuery = useConsultations({ status: statutFilter || undefined });
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

  const columns: DataTableColumn<Consultation>[] = [
    { key: "id", header: "ID", align: "right", accessor: (row) => row.id },
    { key: "patient", header: "Patient", accessor: (row) => patientLabel(row.patient_id) },
    { key: "practitioner", header: "Praticien", accessor: (row) => practitionerLabel(row.practitioner_id) },
    { key: "reason", header: "Motif", accessor: (row) => row.reason },
    {
      key: "statut",
      header: "Statut",
      render: (row) => <Badge status={row.status === "terminee" ? "success" : "accent"}>{STATUS_LABEL[row.status]}</Badge>,
    },
    { key: "created_at", header: "Débutée le", render: (row) => formatDateTime(row.created_at) },
  ];

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>Consultations</CardTitle>
          <Select
            value={statutFilter}
            onChange={(e) => setStatutFilter(e.target.value as ConsultationStatus | "")}
            className="w-40"
            aria-label="Filtrer par statut"
          >
            <option value="">Toutes</option>
            {STATUS_VALUES.map((value) => (
              <option key={value} value={value}>
                {STATUS_LABEL[value]}
              </option>
            ))}
          </Select>
        </CardHeader>
        <CardContent>
          {consultationsQuery.isError ? (
            <ErrorState message={apiErrorMessage(consultationsQuery.error)} onRetry={() => consultationsQuery.refetch()} />
          ) : (
            <DataTable
              columns={columns}
              data={consultationsQuery.data?.data ?? []}
              rowKey={(row) => row.id}
              isLoading={consultationsQuery.isLoading}
              onRowClick={(row) => navigate(`/patients/${row.patient_id}`)}
              emptyState={
                <EmptyState
                  icon={Stethoscope}
                  title="Aucune consultation"
                  description="Aucune consultation n'est visible pour ces critères."
                />
              }
            />
          )}
        </CardContent>
      </Card>
    </div>
  );
}
