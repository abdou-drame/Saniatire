import { Wrench } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { useOverdueMaintenances, useUpcomingMaintenances } from "@/hooks/use-biomedical-equipment";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import type { EquipmentMaintenance } from "@/types/api";

export function EquipmentMaintenanceWidget() {
  const navigate = useNavigate();
  const overdueQuery = useOverdueMaintenances();
  const upcomingQuery = useUpcomingMaintenances();

  const rows = [
    ...(overdueQuery.data ?? []).map((m) => ({ ...m, _overdue: true })),
    ...(upcomingQuery.data ?? []).map((m) => ({ ...m, _overdue: false })),
  ];

  const columns: DataTableColumn<EquipmentMaintenance & { _overdue: boolean }>[] = [
    { key: "equipment", header: "Équipement", accessor: (row) => row.equipment?.nom ?? `Équipement #${row.biomedical_equipment_id}` },
    { key: "date_prevue", header: "Prévue le", accessor: (row) => formatDate(row.date_prevue) },
    {
      key: "statut",
      header: "Statut",
      render: (row) => <Badge status={row._overdue ? "danger" : "warning"}>{row._overdue ? "En retard" : "À venir"}</Badge>,
    },
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Maintenances biomédicales</CardTitle>
        <Button variant="ghost" size="sm" onClick={() => navigate("/equipements")}>
          Voir les équipements
        </Button>
      </CardHeader>
      <CardContent>
        {overdueQuery.isError || upcomingQuery.isError ? (
          <ErrorState
            message={apiErrorMessage(overdueQuery.error ?? upcomingQuery.error)}
            onRetry={() => {
              overdueQuery.refetch();
              upcomingQuery.refetch();
            }}
          />
        ) : (
          <DataTable
            columns={columns}
            data={rows}
            rowKey={(row) => row.id}
            isLoading={overdueQuery.isLoading || upcomingQuery.isLoading}
            emptyState={<EmptyState icon={Wrench} title="Aucune maintenance en retard ou à venir" />}
          />
        )}
      </CardContent>
    </Card>
  );
}
