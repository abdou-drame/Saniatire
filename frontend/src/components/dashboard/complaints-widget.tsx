import { MessageSquareWarning } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { useComplaints } from "@/hooks/use-complaints";
import { usePatientsDirectory } from "@/hooks/use-patients-directory";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import type { Complaint } from "@/types/api";

export function ComplaintsWidget() {
  const navigate = useNavigate();
  const query = useComplaints({ statut: "ouverte" });
  const patientsDirectory = usePatientsDirectory();

  function patientLabel(patientId: number): string {
    const patient = patientsDirectory.byId.get(patientId);
    return patient ? `${patient.first_name} ${patient.last_name}` : `Patient #${patientId}`;
  }

  const columns: DataTableColumn<Complaint>[] = [
    { key: "motif", header: "Motif", accessor: (row) => row.motif },
    { key: "patient", header: "Patient", accessor: (row) => patientLabel(row.patient_id) },
    { key: "service", header: "Service", accessor: (row) => row.service_concerne ?? "—" },
    { key: "created_at", header: "Créée le", accessor: (row) => formatDate(row.created_at) },
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Réclamations ouvertes</CardTitle>
        <Button variant="ghost" size="sm" onClick={() => navigate("/qualite")}>
          Traiter
        </Button>
      </CardHeader>
      <CardContent>
        {query.isError ? (
          <ErrorState message={apiErrorMessage(query.error)} onRetry={() => query.refetch()} />
        ) : (
          <DataTable
            columns={columns}
            data={query.data?.data ?? []}
            rowKey={(row) => row.id}
            isLoading={query.isLoading}
            onRowClick={() => navigate("/qualite")}
            emptyState={<EmptyState icon={MessageSquareWarning} title="Aucune réclamation ouverte" />}
          />
        )}
      </CardContent>
    </Card>
  );
}
