import { Lock, Plus, Users } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { EmployeeProfileFormDialog } from "@/components/personnel/employee-profile-form-dialog";
import { useAuth } from "@/hooks/use-auth";
import { useEmployeeProfiles } from "@/hooks/use-employee-profiles";
import { useSites } from "@/hooks/use-sites";
import { useUsersDirectory } from "@/hooks/use-users-directory";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import { STATUT_EMPLOI_BADGE, STATUT_EMPLOI_LABEL } from "@/pages/personnel/personnel-status";
import type { EmployeeProfile, StatutEmploi } from "@/types/api";

const STATUT_EMPLOI_VALUES: StatutEmploi[] = ["actif", "en_conge", "suspendu", "termine"];

/**
 * Écran Personnel (module RH). Le backend reste seul décideur des règles
 * métier ; ce composant se contente de filtrer/afficher les fiches et de
 * gater les actions de création/édition sur les permissions rh.create /
 * rh.update — jamais sur une déduction locale du rôle de l'utilisateur.
 */
export function PersonnelPage() {
  const { hasPermission } = useAuth();

  const [statutFilter, setStatutFilter] = useState<StatutEmploi | "">("");
  const [siteFilter, setSiteFilter] = useState<number | "">("");
  const [formOpen, setFormOpen] = useState(false);
  const [editingProfile, setEditingProfile] = useState<EmployeeProfile | null>(null);

  const canCreate = hasPermission("rh.create");
  const canUpdate = hasPermission("rh.update");

  const sitesQuery = useSites();
  const usersDirectory = useUsersDirectory(hasPermission("users.view"));
  const profilesQuery = useEmployeeProfiles({
    statutEmploi: statutFilter || undefined,
    siteId: siteFilter || undefined,
  });

  function openCreateDialog() {
    setEditingProfile(null);
    setFormOpen(true);
  }

  function openEditDialog(profile: EmployeeProfile) {
    if (!canUpdate) return;
    setEditingProfile(profile);
    setFormOpen(true);
  }

  const columns: DataTableColumn<EmployeeProfile>[] = [
    {
      key: "employee",
      header: "Employé",
      render: (row) => {
        const user = usersDirectory.byId.get(row.user_id);
        return user ? `${user.first_name} ${user.last_name}` : `Utilisateur #${row.user_id}`;
      },
    },
    { key: "qualification", header: "Qualification", render: (row) => row.qualification ?? "—" },
    {
      key: "date_embauche",
      header: "Date d'embauche",
      render: (row) => (row.date_embauche ? formatDate(row.date_embauche) : "—"),
    },
    { key: "type_contrat", header: "Type de contrat", render: (row) => row.type_contrat ?? "—" },
    {
      key: "statut_emploi",
      header: "Statut",
      render: (row) => (
        <Badge status={STATUT_EMPLOI_BADGE[row.statut_emploi]}>{STATUT_EMPLOI_LABEL[row.statut_emploi]}</Badge>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="font-heading text-xl font-semibold text-text">Personnel</h1>
          <p className="mt-1 text-sm text-text-muted">Fiches RH du personnel de la structure.</p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Fiches personnel</CardTitle>
          {canCreate && (
            <Button size="sm" onClick={openCreateDialog}>
              <Plus size={14} />
              Nouvelle fiche
            </Button>
          )}
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:max-w-md">
            <div>
              <Label>Statut d'emploi</Label>
              <Select
                value={statutFilter}
                onChange={(e) => setStatutFilter(e.target.value as StatutEmploi | "")}
              >
                <option value="">Tous</option>
                {STATUT_EMPLOI_VALUES.map((value) => (
                  <option key={value} value={value}>
                    {STATUT_EMPLOI_LABEL[value]}
                  </option>
                ))}
              </Select>
            </div>
            <div>
              <Label>Site</Label>
              <Select
                value={siteFilter}
                onChange={(e) => setSiteFilter(e.target.value ? Number(e.target.value) : "")}
                disabled={sitesQuery.isLoading}
              >
                <option value="">Tous les sites</option>
                {(sitesQuery.data ?? []).map((site) => (
                  <option key={site.id} value={site.id}>
                    {site.name}
                  </option>
                ))}
              </Select>
            </div>
          </div>

          {profilesQuery.isError ? (
            <ErrorState message={apiErrorMessage(profilesQuery.error)} onRetry={() => profilesQuery.refetch()} />
          ) : (
            <DataTable
              columns={columns}
              data={profilesQuery.data?.data ?? []}
              rowKey={(row) => row.id}
              isLoading={profilesQuery.isLoading}
              onRowClick={canUpdate ? openEditDialog : undefined}
              emptyState={
                <EmptyState
                  icon={Users}
                  title="Aucune fiche personnel"
                  description="Aucune fiche RH n'a encore été créée pour cette structure."
                  actionLabel={canCreate ? "Nouvelle fiche" : undefined}
                  onAction={canCreate ? openCreateDialog : undefined}
                />
              }
            />
          )}
        </CardContent>
      </Card>

      <EmployeeProfileFormDialog open={formOpen} onOpenChange={setFormOpen} profile={editingProfile} />
    </div>
  );
}

export function PersonnelRoute() {
  const { hasPermission } = useAuth();
  if (!hasPermission("rh.view")) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel habilité sur le module RH."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <PersonnelPage />;
}
