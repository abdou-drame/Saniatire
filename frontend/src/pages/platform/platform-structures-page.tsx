import { Building2 } from "lucide-react";
import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { CreateStructureDialog } from "@/components/platform/create-structure-dialog";
import { usePlatformStructures } from "@/hooks/use-platform-structures";
import { apiErrorMessage } from "@/lib/api-error";
import type { Structure } from "@/types/api";

const STRUCTURE_TYPE_LABEL: Record<string, string> = {
  cabinet: "Cabinet",
  centre_specialise: "Centre spécialisé",
  laboratoire: "Laboratoire",
  imagerie: "Imagerie",
  clinique: "Clinique",
  polyclinique: "Polyclinique",
  groupe_sante: "Groupe santé",
};

export function PlatformStructuresPage() {
  const { data: structures, isLoading, isError, error, refetch } = usePlatformStructures();
  const [createOpen, setCreateOpen] = useState(false);
  const navigate = useNavigate();

  const columns: DataTableColumn<Structure>[] = [
    { key: "legal_name", header: "Nom", accessor: (s) => s.legal_name, sortable: true },
    { key: "code", header: "Code", accessor: (s) => s.code, sortable: true },
    { key: "type", header: "Type", accessor: (s) => STRUCTURE_TYPE_LABEL[s.type] ?? s.type },
    { key: "city", header: "Ville", accessor: (s) => s.city ?? "—" },
    {
      key: "is_active",
      header: "Statut",
      render: (s) => (
        <Badge status={s.is_active ? "success" : "neutral"}>{s.is_active ? "Active" : "Inactive"}</Badge>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>Structures</CardTitle>
          <Button onClick={() => setCreateOpen(true)}>Nouvelle structure</Button>
        </CardHeader>
        <CardContent>
          {isError ? (
            <ErrorState message={apiErrorMessage(error)} onRetry={() => refetch()} />
          ) : (
            <DataTable
              columns={columns}
              data={structures ?? []}
              rowKey={(s) => s.id}
              isLoading={isLoading}
              onRowClick={(s) => navigate(`/platform/structures/${s.id}`)}
              emptyState={
                <EmptyState
                  icon={Building2}
                  title="Aucune structure"
                  description="Créez la première structure cliente de la plateforme."
                  actionLabel="Nouvelle structure"
                  onAction={() => setCreateOpen(true)}
                />
              }
            />
          )}
        </CardContent>
      </Card>

      <CreateStructureDialog open={createOpen} onOpenChange={setCreateOpen} />
    </div>
  );
}
