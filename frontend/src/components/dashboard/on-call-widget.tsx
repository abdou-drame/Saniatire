import { ShieldAlert } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { useAuth } from "@/hooks/use-auth";
import { useOnCallNow } from "@/hooks/use-on-call";
import { useUsersDirectory } from "@/hooks/use-users-directory";
import { apiErrorMessage } from "@/lib/api-error";
import type { WorkSchedule, WorkScheduleType } from "@/types/api";

const TYPE_LABEL: Record<WorkScheduleType, string> = {
  normal: "Travail",
  garde: "Garde",
  astreinte: "Astreinte",
};

/** GET /on-call/now est ouvert à tout utilisateur authentifié (urgence). */
export function OnCallWidget() {
  const navigate = useNavigate();
  const { hasPermission } = useAuth();
  const query = useOnCallNow();
  const usersDirectory = useUsersDirectory(hasPermission("users.view"));

  function userLabel(userId: number): string {
    const staffUser = usersDirectory.byId.get(userId);
    return staffUser ? `${staffUser.first_name} ${staffUser.last_name}` : `Utilisateur #${userId}`;
  }

  const columns: DataTableColumn<WorkSchedule>[] = [
    { key: "user", header: "Personnel", accessor: (row) => userLabel(row.user_id) },
    { key: "type", header: "Type", render: (row) => <Badge status="accent">{TYPE_LABEL[row.type]}</Badge> },
    { key: "heures", header: "Horaires", accessor: (row) => `${row.heure_debut} – ${row.heure_fin}` },
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Garde / astreinte en cours</CardTitle>
        <Button variant="ghost" size="sm" onClick={() => navigate("/gardes-astreintes")}>
          Voir le planning
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
            emptyState={<EmptyState icon={ShieldAlert} title="Personne de garde actuellement" />}
          />
        )}
      </CardContent>
    </Card>
  );
}
