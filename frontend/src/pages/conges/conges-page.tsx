import { CalendarOff, ClipboardCheck, Plus } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { LeaveRequestFormDialog } from "@/components/conges/leave-request-form-dialog";
import { useAuth } from "@/hooks/use-auth";
import { useLeaveRequests, useRefuseLeaveRequest, useValidateLeaveRequest } from "@/hooks/use-leave-requests";
import { useUsersDirectory } from "@/hooks/use-users-directory";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import { LEAVE_STATUT_BADGE, LEAVE_STATUT_LABEL, LEAVE_TYPE_LABEL, SCHEDULE_TYPE_LABEL } from "@/pages/conges/conges-status";
import type { LeaveRequest, LeaveRequestOverlapWarning } from "@/types/api";

/**
 * Écran Congés. Comme pour Achats/Personnel, le backend reste seul décideur
 * des règles métier :
 *  - Section "Mes demandes" affiche exactement useLeaveRequests({ userId })
 *    pour l'utilisateur courant.
 *  - Section "Validation" affiche exactement useLeaveRequests({ statut:
 *    "demande" }). Attention : LeaveRequestController::index() ne restreint
 *    PAS cette liste au périmètre d'équipe — un détenteur de conges.view (dont
 *    le rôle manager) voit délibérément toutes les demandes de la structure,
 *    pour pouvoir coordonner les plannings. Le périmètre d'équipe n'est
 *    appliqué qu'au moment de décider, par assertCanDecide() sur
 *    validate/refuse, qui renvoie alors un 403 « Vous ne pouvez valider que
 *    les congés des utilisateurs de votre équipe. » — affiché tel quel par
 *    handleApprove/handleRefuse. Ce composant n'ajoute, ne recalcule et ne
 *    devine JAMAIS un filtre supplémentaire côté client sur ce périmètre :
 *    c'est le backend, et lui seul, qui refuse la décision.
 */
export function CongesPage() {
  const { user, hasPermission } = useAuth();

  return (
    <div className="space-y-6">
      <div>
        <h1 className="font-heading text-xl font-semibold text-text">Congés</h1>
        <p className="mt-1 text-sm text-text-muted">Demandes de congé et validation des demandes en attente.</p>
      </div>

      <MesDemandesSection userId={user?.id ?? null} />

      {hasPermission("conges.validate") && <ValidationSection />}
    </div>
  );
}

/** Pas de gate de permission au niveau route : tout utilisateur authentifié peut
 * consulter/déposer ses propres demandes de congé. */
export function CongesRoute() {
  return <CongesPage />;
}

const LEAVE_COLUMNS_BASE: DataTableColumn<LeaveRequest>[] = [
  { key: "type", header: "Type", render: (row) => LEAVE_TYPE_LABEL[row.type] },
  { key: "date_debut", header: "Début", render: (row) => formatDate(row.date_debut) },
  { key: "date_fin", header: "Fin", render: (row) => formatDate(row.date_fin) },
  {
    key: "statut",
    header: "Statut",
    render: (row) => <Badge status={LEAVE_STATUT_BADGE[row.statut]}>{LEAVE_STATUT_LABEL[row.statut]}</Badge>,
  },
  { key: "commentaire", header: "Commentaire", render: (row) => row.commentaire ?? "—" },
];

function MesDemandesSection({ userId }: { userId: number | null }) {
  const [createOpen, setCreateOpen] = useState(false);
  const requestsQuery = useLeaveRequests(userId ? { userId } : {});

  return (
    <Card>
      <CardHeader>
        <CardTitle>Mes demandes de congé</CardTitle>
        <Button size="sm" onClick={() => setCreateOpen(true)}>
          <Plus size={14} />
          Nouvelle demande
        </Button>
      </CardHeader>
      <CardContent>
        {requestsQuery.isError ? (
          <ErrorState message={apiErrorMessage(requestsQuery.error)} onRetry={() => requestsQuery.refetch()} />
        ) : (
          <DataTable
            columns={LEAVE_COLUMNS_BASE}
            data={requestsQuery.data?.data ?? []}
            rowKey={(row) => row.id}
            isLoading={requestsQuery.isLoading}
            emptyState={
              <EmptyState
                icon={CalendarOff}
                title="Aucune demande de congé"
                description="Vous n'avez encore déposé aucune demande de congé."
                actionLabel="Nouvelle demande"
                onAction={() => setCreateOpen(true)}
              />
            }
          />
        )}
      </CardContent>

      <LeaveRequestFormDialog open={createOpen} onOpenChange={setCreateOpen} />
    </Card>
  );
}

interface ApprovalWarningEntry {
  requestId: number;
  requesterLabel: string;
  warnings: LeaveRequestOverlapWarning[];
}

function ValidationSection() {
  const { hasPermission } = useAuth();
  const usersDirectory = useUsersDirectory(hasPermission("users.view"));

  // Exactement ce que le backend renvoie pour ce statut — aucun filtre
  // client-side supplémentaire n'est appliqué à `pendingQuery.data`.
  const pendingQuery = useLeaveRequests({ statut: "demande" });

  const validateRequest = useValidateLeaveRequest();
  const refuseRequest = useRefuseLeaveRequest();

  const [actionError, setActionError] = useState<string | null>(null);
  // Les warnings de chevauchement doivent rester visibles même une fois la
  // ligne approuvée disparue de la liste "demande" (elle passe à "valide" et
  // sort donc du filtre) — on les garde dans un état local persistant plutôt
  // que de les afficher dans la ligne elle-même.
  const [approvalWarnings, setApprovalWarnings] = useState<ApprovalWarningEntry[]>([]);

  function requesterLabel(userId: number): string {
    const staffUser = usersDirectory.byId.get(userId);
    return staffUser ? `${staffUser.first_name} ${staffUser.last_name}` : `Utilisateur #${userId}`;
  }

  async function handleApprove(row: LeaveRequest) {
    setActionError(null);
    try {
      const result = await validateRequest.mutateAsync(row.id);
      if (result.warnings.length > 0) {
        setApprovalWarnings((prev) => [
          ...prev,
          { requestId: row.id, requesterLabel: requesterLabel(row.user_id), warnings: result.warnings },
        ]);
      }
    } catch (error) {
      setActionError(apiErrorMessage(error));
    }
  }

  async function handleRefuse(row: LeaveRequest) {
    setActionError(null);
    try {
      await refuseRequest.mutateAsync(row.id);
    } catch (error) {
      setActionError(apiErrorMessage(error));
    }
  }

  function dismissWarning(requestId: number) {
    setApprovalWarnings((prev) => prev.filter((entry) => entry.requestId !== requestId));
  }

  const columns: DataTableColumn<LeaveRequest>[] = [
    { key: "requester", header: "Demandeur", render: (row) => requesterLabel(row.user_id) },
    ...LEAVE_COLUMNS_BASE.filter((column) => column.key !== "statut"),
    {
      key: "actions",
      header: "Actions",
      align: "right",
      render: (row) => (
        <div className="flex justify-end gap-2">
          <Button
            size="sm"
            onClick={() => handleApprove(row)}
            disabled={validateRequest.isPending || refuseRequest.isPending}
          >
            Approuver
          </Button>
          <Button
            size="sm"
            variant="secondary"
            onClick={() => handleRefuse(row)}
            disabled={validateRequest.isPending || refuseRequest.isPending}
          >
            Refuser
          </Button>
        </div>
      ),
    },
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Validation des congés</CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        {actionError && (
          <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
            {actionError}
          </p>
        )}

        {approvalWarnings.map((entry) => (
          <div
            key={entry.requestId}
            className="space-y-1 rounded-md border border-warning/30 bg-warning/10 px-3 py-2.5 text-sm text-text"
          >
            <div className="flex items-start justify-between gap-3">
              <p className="font-medium text-warning">
                Demande de {entry.requesterLabel} approuvée avec avertissement
              </p>
              <button
                type="button"
                onClick={() => dismissWarning(entry.requestId)}
                className="text-xs text-text-subtle hover:text-text-muted"
              >
                Masquer
              </button>
            </div>
            <ul className="list-disc space-y-0.5 pl-5 text-xs text-text-muted">
              {entry.warnings.map((warning, index) => (
                <li key={`${warning.work_schedule_id}-${index}`}>
                  Chevauche un horaire du {formatDate(warning.date)} ({SCHEDULE_TYPE_LABEL[warning.schedule_type]})
                </li>
              ))}
            </ul>
          </div>
        ))}

        {pendingQuery.isError ? (
          <ErrorState message={apiErrorMessage(pendingQuery.error)} onRetry={() => pendingQuery.refetch()} />
        ) : (
          <DataTable
            columns={columns}
            data={pendingQuery.data?.data ?? []}
            rowKey={(row) => row.id}
            isLoading={pendingQuery.isLoading}
            emptyState={
              <EmptyState
                icon={ClipboardCheck}
                title="Aucune demande en attente de validation"
                description="Aucune demande de congé n'attend actuellement votre validation."
              />
            }
          />
        )}
      </CardContent>
    </Card>
  );
}
