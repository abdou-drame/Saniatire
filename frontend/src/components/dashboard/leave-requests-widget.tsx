import { CalendarOff } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { useAuth } from "@/hooks/use-auth";
import { useLeaveRequests } from "@/hooks/use-leave-requests";
import { useUsersDirectory } from "@/hooks/use-users-directory";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import type { LeaveRequest, LeaveRequestType } from "@/types/api";

const TYPE_LABEL: Record<LeaveRequestType, string> = {
  conge_annuel: "Congé annuel",
  maladie: "Maladie",
  autre: "Autre",
};

export function LeaveRequestsWidget() {
  const navigate = useNavigate();
  const { hasPermission } = useAuth();
  const query = useLeaveRequests({ statut: "demande" });
  const usersDirectory = useUsersDirectory(hasPermission("users.view"));

  function requesterLabel(userId: number): string {
    const staffUser = usersDirectory.byId.get(userId);
    return staffUser ? `${staffUser.first_name} ${staffUser.last_name}` : `Utilisateur #${userId}`;
  }

  const columns: DataTableColumn<LeaveRequest>[] = [
    { key: "user", header: "Demandeur", accessor: (row) => requesterLabel(row.user_id) },
    { key: "type", header: "Type", accessor: (row) => TYPE_LABEL[row.type] },
    { key: "date_debut", header: "Du", accessor: (row) => formatDate(row.date_debut) },
    { key: "date_fin", header: "Au", accessor: (row) => formatDate(row.date_fin) },
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Demandes de congés en attente</CardTitle>
        <Button variant="ghost" size="sm" onClick={() => navigate("/conges")}>
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
            onRowClick={() => navigate("/conges")}
            emptyState={<EmptyState icon={CalendarOff} title="Aucune demande en attente" />}
          />
        )}
      </CardContent>
    </Card>
  );
}
