import { ChevronLeft, ChevronRight, ClipboardList, Lock, ShieldCheck } from "lucide-react";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { useAuditLogs, type AuditLogFilters } from "@/hooks/use-audit-logs";
import { useAuth } from "@/hooks/use-auth";
import { useUsersDirectory } from "@/hooks/use-users-directory";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDateTime } from "@/lib/datetime";
import type { AuditLogEntry } from "@/types/api";

type UsersDirectory = ReturnType<typeof useUsersDirectory>;

/** Dernier segment de subject_type (nom de classe FQCN) + #id — ex.
 * "App\\Domain\\Notification\\Models\\NotificationTemplate" -> "NotificationTemplate #5". */
function subjectLabel(subjectType: string | null, subjectId: number | null): string {
  if (!subjectType) return "—";
  const shortName = subjectType.split("\\").pop() || subjectType;
  return subjectId !== null ? `${shortName} #${subjectId}` : shortName;
}

/** causer_id null = action système (pas d'utilisateur authentifié à l'origine,
 * ex. job planifié) — jamais confondu avec "utilisateur inconnu". Repli
 * `Utilisateur #id` si l'annuaire n'est pas chargé (pas de permission
 * users.view, cf. use-users-directory.ts) ou si l'id n'y figure pas. */
function userLabel(directory: UsersDirectory, causerId: number | null): string {
  if (causerId === null) return "Système";
  const user = directory.byId.get(causerId);
  if (!user) return `Utilisateur #${causerId}`;
  return `${user.first_name} ${user.last_name}`;
}

/**
 * Journal en lecture seule — bandeau permanent rappelant la garantie
 * serveur réelle (AppServiceProvider::boot() lève une exception sur toute
 * tentative de modification/suppression d'une Activity, aucune route
 * PATCH/PUT/DELETE n'existe sur /audit-logs). Ce texte est honnête : ce
 * n'est pas une simple omission d'UI, c'est une garantie backend.
 */
function ReadOnlyBanner() {
  return (
    <div className="flex items-start gap-3 rounded-lg border border-border bg-surface-hover/40 px-4 py-3">
      <ShieldCheck size={20} className="mt-0.5 shrink-0 text-text-muted" />
      <p className="text-sm text-text-muted">
        Journal en lecture seule — infalsifiable : toute tentative de modification ou de suppression d'une entrée est
        rejetée côté serveur, y compris pour un compte administrateur.
      </p>
    </div>
  );
}

export function AuditPage() {
  const { hasPermission } = useAuth();
  const canViewUsers = hasPermission("users.view");
  const usersDirectory = useUsersDirectory(canViewUsers);

  const [userIdFilter, setUserIdFilter] = useState("");
  const [actionFilter, setActionFilter] = useState("");
  const [tableFilter, setTableFilter] = useState("");
  const [fromFilter, setFromFilter] = useState("");
  const [toFilter, setToFilter] = useState("");
  const [pageNumber, setPageNumber] = useState(1);
  const [selected, setSelected] = useState<AuditLogEntry | null>(null);

  // Tout changement de filtre repart de la page 1 — sinon on pourrait se
  // retrouver sur une page au-delà de last_page pour le nouveau filtrage.
  useEffect(() => {
    setPageNumber(1);
  }, [userIdFilter, actionFilter, tableFilter, fromFilter, toFilter]);

  const filters: AuditLogFilters = {
    user_id: userIdFilter ? Number(userIdFilter) : undefined,
    action: actionFilter.trim() || undefined,
    table: tableFilter.trim() || undefined,
    from: fromFilter || undefined,
    to: toFilter || undefined,
    page: pageNumber,
  };

  const auditQuery = useAuditLogs(filters);
  const page = auditQuery.data;

  function resetFilters() {
    setUserIdFilter("");
    setActionFilter("");
    setTableFilter("");
    setFromFilter("");
    setToFilter("");
    setPageNumber(1);
  }

  const columns: DataTableColumn<AuditLogEntry>[] = [
    {
      key: "created_at",
      header: "Date",
      render: (row) => formatDateTime(row.created_at),
    },
    {
      key: "user",
      header: "Utilisateur",
      render: (row) => userLabel(usersDirectory, row.causer_id),
    },
    {
      key: "action",
      header: "Action",
      render: (row) => {
        const { description, event } = row;
        if (description && event && description !== event) {
          return (
            <div className="space-y-0.5">
              <p className="text-text">{description}</p>
              <p className="text-xs text-text-subtle">{event}</p>
            </div>
          );
        }
        return description ?? event ?? "—";
      },
    },
    {
      key: "subject",
      header: "Table concernée",
      render: (row) => subjectLabel(row.subject_type, row.subject_id),
    },
    {
      key: "details",
      header: "Détails",
      render: (row) => (
        <Button variant="secondary" size="sm" onClick={() => setSelected(row)}>
          Voir
        </Button>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="font-heading text-xl font-semibold text-text">Audit / Conformité</h1>
        <p className="mt-1 text-sm text-text-muted">
          Journal des actions effectuées dans l'application (créations, modifications, suppressions).
        </p>
      </div>

      <ReadOnlyBanner />

      <Card>
        <CardHeader>
          <CardTitle>Filtres</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div>
              <Label>Utilisateur</Label>
              <Select value={userIdFilter} onChange={(e) => setUserIdFilter(e.target.value)}>
                <option value="">Tous les utilisateurs</option>
                {(usersDirectory.data ?? []).map((u) => (
                  <option key={u.id} value={u.id}>
                    {u.first_name} {u.last_name}
                  </option>
                ))}
              </Select>
            </div>
            <div>
              <Label>Action</Label>
              <Input
                value={actionFilter}
                onChange={(e) => setActionFilter(e.target.value)}
                placeholder="ex. updated"
              />
            </div>
            <div>
              <Label>Table concernée</Label>
              <Input
                value={tableFilter}
                onChange={(e) => setTableFilter(e.target.value)}
                placeholder="ex. Consultation"
              />
            </div>
            <div>
              <Label>Du</Label>
              <Input type="date" value={fromFilter} onChange={(e) => setFromFilter(e.target.value)} />
            </div>
            <div>
              <Label>Au</Label>
              <Input type="date" value={toFilter} onChange={(e) => setToFilter(e.target.value)} />
            </div>
          </div>
          <Button variant="secondary" size="sm" onClick={resetFilters}>
            Réinitialiser les filtres
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Journal d'activité</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {auditQuery.isError ? (
            <ErrorState message={apiErrorMessage(auditQuery.error)} onRetry={() => auditQuery.refetch()} />
          ) : (
            <>
              <DataTable
                columns={columns}
                data={page?.data ?? []}
                rowKey={(row) => row.id}
                isLoading={auditQuery.isLoading}
                // pageSize = nombre d'éléments de la page serveur courante :
                // ceil(count/pageSize) vaut toujours 1, donc la pagination
                // CLIENT interne de DataTable ne s'affiche jamais — la
                // pagination affichée ci-dessous est pilotée par le state
                // serveur (current_page/last_page/total), pas par DataTable.
                pageSize={Math.max(page?.data.length ?? 1, 1)}
                emptyState={
                  <EmptyState
                    icon={ClipboardList}
                    title="Aucune entrée"
                    description="Aucune action du journal ne correspond à ces filtres."
                  />
                }
              />

              {page && page.total > 0 && (
                <div className="flex items-center justify-between border-t border-border pt-3 text-xs text-text-muted">
                  <span>
                    Page {page.current_page} / {page.last_page} — {page.total} résultats
                  </span>
                  <div className="flex items-center gap-1">
                    <button
                      className="rounded p-1 hover:bg-surface-hover disabled:pointer-events-none disabled:opacity-40"
                      disabled={page.current_page <= 1}
                      onClick={() => setPageNumber((p) => Math.max(1, p - 1))}
                      aria-label="Page précédente"
                    >
                      <ChevronLeft size={16} />
                    </button>
                    <button
                      className="rounded p-1 hover:bg-surface-hover disabled:pointer-events-none disabled:opacity-40"
                      disabled={page.current_page >= page.last_page}
                      onClick={() => setPageNumber((p) => Math.min(page.last_page, p + 1))}
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

      <Dialog open={selected !== null} onOpenChange={(open) => !open && setSelected(null)}>
        <DialogContent className="max-w-2xl">
          {selected && (
            <>
              <DialogHeader>
                <DialogTitle>Détails de l'entrée #{selected.id}</DialogTitle>
              </DialogHeader>
              <div className="space-y-4">
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Nouvelles valeurs</p>
                  <pre className="mt-1 max-h-64 overflow-auto rounded-md bg-surface-hover p-3 text-xs text-text">
                    {JSON.stringify(selected.properties?.attributes ?? {}, null, 2)}
                  </pre>
                </div>
                {selected.properties?.old != null && (
                  <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Anciennes valeurs</p>
                    <pre className="mt-1 max-h-64 overflow-auto rounded-md bg-surface-hover p-3 text-xs text-text">
                      {JSON.stringify(selected.properties.old, null, 2)}
                    </pre>
                  </div>
                )}
              </div>
            </>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}

/**
 * Gardée sur audit.view (patron ReferencementRoute) : côté serveur, seuls
 * les rôles conformite et administrateur (via son wildcard *) ont cette
 * permission — direction et directeur_medical ne l'ont volontairement pas
 * (voir app/Domain/Audit/README.md). Aucune action d'écriture n'existe sur
 * cet écran : pas de bouton, pas de handler, pas d'appel POST/PATCH/DELETE.
 */
export function AuditRoute() {
  const { hasPermission } = useAuth();
  if (!hasPermission("audit.view")) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel habilité sur le module Audit / Conformité."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <AuditPage />;
}
