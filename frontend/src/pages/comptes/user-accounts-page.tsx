import { Lock, Plus, Users } from "lucide-react";
import { useMemo, useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { UserAccountFormDialog } from "@/components/comptes/user-account-form-dialog";
import { EmployeeProfileFormDialog } from "@/components/personnel/employee-profile-form-dialog";
import { useAuth } from "@/hooks/use-auth";
import { useUserAccounts, useUserRoles } from "@/hooks/use-user-accounts";
import { roleLabel } from "@/config/role-labels";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDateTime } from "@/lib/datetime";
import type { UserAccount } from "@/types/api";

/**
 * Écran Comptes utilisateurs — distinct de /personnel (fiches RH). Gère les
 * comptes de connexion (User) : création avec mot de passe initial saisi
 * (mécanisme retenu après discussion — cf. commentaire dans
 * user-account-form-dialog.tsx), rôle, sites, statut actif/inactif. Aucune
 * suppression n'est proposée ici : la désactivation (is_active=false) est
 * l'action réversible attendue, cohérente avec le fait que seul
 * l'administrateur détient users.delete côté backend.
 */
export function UserAccountsPage() {
  const { hasPermission } = useAuth();
  const canCreate = hasPermission("users.create");
  const canUpdate = hasPermission("users.update");

  const accountsQuery = useUserAccounts();
  const rolesQuery = useUserRoles();

  const [search, setSearch] = useState("");
  const [roleFilter, setRoleFilter] = useState("");
  const [statusFilter, setStatusFilter] = useState<"" | "actif" | "inactif">("");
  const [formOpen, setFormOpen] = useState(false);
  const [editingAccount, setEditingAccount] = useState<UserAccount | null>(null);
  const [rhDialogUserId, setRhDialogUserId] = useState<number | null>(null);

  function openCreateDialog() {
    setEditingAccount(null);
    setFormOpen(true);
  }

  function openEditDialog(account: UserAccount) {
    if (!canUpdate) return;
    setEditingAccount(account);
    setFormOpen(true);
  }

  const filtered = useMemo(() => {
    const term = search.trim().toLowerCase();
    return (accountsQuery.data ?? []).filter((account) => {
      if (roleFilter && !account.roles.includes(roleFilter)) return false;
      if (statusFilter === "actif" && !account.is_active) return false;
      if (statusFilter === "inactif" && account.is_active) return false;
      if (!term) return true;
      const haystack = `${account.first_name} ${account.last_name} ${account.email}`.toLowerCase();
      return haystack.includes(term);
    });
  }, [accountsQuery.data, search, roleFilter, statusFilter]);

  const columns: DataTableColumn<UserAccount>[] = [
    {
      key: "name",
      header: "Nom",
      render: (row) => (
        <div>
          <p className="font-medium text-text">
            {row.first_name} {row.last_name}
          </p>
          <p className="text-xs text-text-muted">{row.email}</p>
        </div>
      ),
    },
    {
      key: "roles",
      header: "Rôle(s)",
      render: (row) => (
        <div className="flex flex-wrap gap-1">
          {row.roles.map((r) => (
            <Badge key={r} status="neutral" dot={false}>
              {roleLabel(r)}
            </Badge>
          ))}
        </div>
      ),
    },
    {
      key: "sites",
      header: "Site(s)",
      render: (row) => (row.sites.length > 0 ? row.sites.map((s) => s.name).join(", ") : "—"),
    },
    {
      key: "status",
      header: "Statut",
      render: (row) => (
        <Badge status={row.is_active ? "success" : "neutral"}>{row.is_active ? "Actif" : "Inactif"}</Badge>
      ),
    },
    {
      key: "two_factor",
      header: "2FA",
      render: (row) =>
        row.two_factor_required ? (
          <Badge status={row.two_factor_enabled ? "success" : "warning"}>
            {row.two_factor_enabled ? "Activée" : "À configurer"}
          </Badge>
        ) : (
          <span className="text-xs text-text-subtle">—</span>
        ),
    },
    {
      key: "last_login",
      header: "Dernière connexion",
      render: (row) => (row.last_login_at ? formatDateTime(row.last_login_at) : "Jamais"),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="font-heading text-xl font-semibold text-text">Comptes utilisateurs</h1>
          <p className="mt-1 text-sm text-text-muted">
            Comptes de connexion du personnel — rôle, sites et statut d'accès.
          </p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Comptes</CardTitle>
          {canCreate && (
            <Button size="sm" onClick={openCreateDialog}>
              <Plus size={14} />
              Nouveau compte
            </Button>
          )}
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
              <Label>Recherche</Label>
              <Input
                placeholder="Nom ou email..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
            </div>
            <div>
              <Label>Rôle</Label>
              <Select value={roleFilter} onChange={(e) => setRoleFilter(e.target.value)} disabled={rolesQuery.isLoading}>
                <option value="">Tous les rôles</option>
                {(rolesQuery.data ?? []).map((r) => (
                  <option key={r} value={r}>
                    {roleLabel(r)}
                  </option>
                ))}
              </Select>
            </div>
            <div>
              <Label>Statut</Label>
              <Select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value as "" | "actif" | "inactif")}>
                <option value="">Tous</option>
                <option value="actif">Actif</option>
                <option value="inactif">Inactif</option>
              </Select>
            </div>
          </div>

          {accountsQuery.isError ? (
            <ErrorState message={apiErrorMessage(accountsQuery.error)} onRetry={() => accountsQuery.refetch()} />
          ) : (
            <DataTable
              columns={columns}
              data={filtered}
              rowKey={(row) => row.id}
              isLoading={accountsQuery.isLoading}
              onRowClick={canUpdate ? openEditDialog : undefined}
              emptyState={
                <EmptyState
                  icon={Users}
                  title="Aucun compte trouvé"
                  description="Aucun compte ne correspond aux filtres sélectionnés."
                  actionLabel={canCreate ? "Nouveau compte" : undefined}
                  onAction={canCreate ? openCreateDialog : undefined}
                />
              }
            />
          )}
        </CardContent>
      </Card>

      <UserAccountFormDialog
        open={formOpen}
        onOpenChange={setFormOpen}
        account={editingAccount}
        onRequestEmployeeProfile={hasPermission("rh.create") ? (userId) => setRhDialogUserId(userId) : undefined}
      />

      {rhDialogUserId !== null && (
        <EmployeeProfileFormDialog
          open={rhDialogUserId !== null}
          onOpenChange={(open) => {
            if (!open) setRhDialogUserId(null);
          }}
          initialUserId={rhDialogUserId}
        />
      )}
    </div>
  );
}

export function UserAccountsRoute() {
  const { hasPermission } = useAuth();
  if (!hasPermission("users.view")) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel habilité sur la gestion des comptes."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <UserAccountsPage />;
}
