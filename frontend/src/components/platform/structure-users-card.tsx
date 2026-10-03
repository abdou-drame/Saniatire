import { ChevronLeft, ChevronRight, Users } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { roleLabel } from "@/config/role-labels";
import { usePlatformStructureUsers } from "@/hooks/use-platform-insights";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDateTime } from "@/lib/datetime";
import type { PlatformStructureUser } from "@/types/api";

const COLUMNS: DataTableColumn<PlatformStructureUser>[] = [
  {
    key: "name",
    header: "Nom",
    render: (row) => (
      <span className="whitespace-nowrap">
        {row.first_name} {row.last_name}
      </span>
    ),
  },
  { key: "email", header: "E-mail", render: (row) => <span className="whitespace-nowrap">{row.email}</span> },
  {
    key: "roles",
    header: "Rôles",
    render: (row) =>
      row.roles.length === 0 ? (
        "—"
      ) : (
        <div className="flex flex-wrap gap-1">
          {row.roles.map((role) => (
            <Badge key={role} status="accent" dot={false} className="whitespace-nowrap">
              {roleLabel(role)}
            </Badge>
          ))}
        </div>
      ),
  },
  {
    key: "status",
    header: "Statut",
    render: (row) => (
      <div className="flex flex-wrap gap-1">
        <Badge status={row.is_active ? "success" : "neutral"}>{row.is_active ? "Actif" : "Désactivé"}</Badge>
        {row.is_locked && (
          <Badge
            status="danger"
            title={row.locked_until ? `Verrouillé jusqu'au ${formatDateTime(row.locked_until)}` : undefined}
          >
            Verrouillé
          </Badge>
        )}
      </div>
    ),
  },
  {
    key: "last_login_at",
    header: "Dernière connexion",
    render: (row) => (
      <span className="whitespace-nowrap">{row.last_login_at ? formatDateTime(row.last_login_at) : "Jamais"}</span>
    ),
  },
];

/**
 * Personnel d'une structure, en lecture seule : les actions (déblocage,
 * réinitialisation, désactivation) restent sur la carte Administrateurs.
 */
export function StructureUsersCard({ structureId }: { structureId: number }) {
  const [pageNumber, setPageNumber] = useState(1);
  const usersQuery = usePlatformStructureUsers(structureId, pageNumber);
  const page = usersQuery.data;

  return (
    <Card>
      <CardHeader>
        <CardTitle>Utilisateurs</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {usersQuery.isError ? (
          <ErrorState message={apiErrorMessage(usersQuery.error)} onRetry={() => usersQuery.refetch()} />
        ) : (
          <>
            <DataTable
              columns={COLUMNS}
              data={page?.data ?? []}
              rowKey={(row) => row.id}
              isLoading={usersQuery.isLoading}
              pageSize={Math.max(page?.data.length ?? 1, 1)}
              emptyState={<EmptyState icon={Users} title="Aucun utilisateur" className="py-10" />}
            />

            {page && page.meta.total > 0 && (
              <div className="flex items-center justify-between border-t border-border pt-3 text-xs text-text-muted">
                <span>
                  Page {page.meta.current_page} / {page.meta.last_page} — {page.meta.total} utilisateur
                  {page.meta.total > 1 ? "s" : ""}
                </span>
                <div className="flex items-center gap-1">
                  <button
                    className="rounded p-1 hover:bg-surface-hover disabled:pointer-events-none disabled:opacity-40"
                    disabled={page.meta.current_page <= 1 || usersQuery.isFetching}
                    onClick={() => setPageNumber((p) => Math.max(1, p - 1))}
                    aria-label="Page précédente"
                  >
                    <ChevronLeft size={16} />
                  </button>
                  <button
                    className="rounded p-1 hover:bg-surface-hover disabled:pointer-events-none disabled:opacity-40"
                    disabled={page.meta.current_page >= page.meta.last_page || usersQuery.isFetching}
                    onClick={() => setPageNumber((p) => Math.min(page.meta.last_page, p + 1))}
                    aria-label="Page suivante"
                  >
                    <ChevronRight size={16} />
                  </button>
                </div>
              </div>
            )}
          </>
        )}
      </CardContent>
    </Card>
  );
}
