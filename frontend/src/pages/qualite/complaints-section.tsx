import { LoaderCircle, MessageSquareWarning, Plus } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Skeleton } from "@/components/ui/loading-state";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { CreateComplaintDialog } from "@/components/qualite/create-complaint-dialog";
import { useAuth } from "@/hooks/use-auth";
import {
  useAssignComplaint,
  useCloseComplaint,
  useComplaint,
  useComplaints,
  useRespondToComplaint,
  useResolveComplaint,
} from "@/hooks/use-complaints";
import { usePatientsDirectory } from "@/hooks/use-patients-directory";
import { useUsersDirectory } from "@/hooks/use-users-directory";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate, formatDateTime } from "@/lib/datetime";
import {
  COMPLAINT_ORIGIN_BADGE,
  COMPLAINT_ORIGIN_LABEL,
  COMPLAINT_STATUT_BADGE,
  COMPLAINT_STATUT_LABEL,
} from "@/pages/qualite/qualite-status";
import type { Complaint, ComplaintStatut } from "@/types/api";

type PatientsDirectory = ReturnType<typeof usePatientsDirectory>;
type UsersDirectory = ReturnType<typeof useUsersDirectory>;

const COMPLAINT_STATUT_VALUES: ComplaintStatut[] = ["ouverte", "en_cours", "resolue", "close"];

function patientLabel(directory: PatientsDirectory, patientId: number): string {
  const patient = directory.byId.get(patientId);
  return patient ? `${patient.first_name} ${patient.last_name}` : `Patient #${patientId}`;
}

function gestionnaireLabel(directory: UsersDirectory, gestionnaireId: number | null, label?: string | null): string {
  if (gestionnaireId === null) return "Non assignée";
  if (label) return label;
  const user = directory.byId.get(gestionnaireId);
  return user ? `${user.first_name} ${user.last_name}` : `Utilisateur #${gestionnaireId}`;
}

/**
 * Section Réclamations — toujours rendue (le périmètre est déjà assuré côté
 * serveur par ComplaintController::index, qui ne renvoie que les
 * réclamations visibles à l'utilisateur courant). Seuls le bouton "Nouvelle
 * réclamation" et l'action "Assigner" du détail se gardent individuellement
 * par permission.
 */
export function ComplaintsSection() {
  const { hasPermission } = useAuth();

  const [statutFilter, setStatutFilter] = useState<ComplaintStatut | "">("");
  const [selectedComplaintId, setSelectedComplaintId] = useState<number | null>(null);
  const [createOpen, setCreateOpen] = useState(false);

  const complaintsQuery = useComplaints({ statut: statutFilter || undefined });
  const patientsDirectory = usePatientsDirectory();
  const usersDirectory = useUsersDirectory(hasPermission("users.view"));

  const canCreate = hasPermission("reclamations.create");

  const columns: DataTableColumn<Complaint>[] = [
    { key: "id", header: "ID", align: "right", accessor: (row) => row.id },
    { key: "motif", header: "Motif", accessor: (row) => row.motif },
    { key: "service", header: "Service", render: (row) => row.service_concerne ?? "—" },
    {
      key: "origin",
      header: "Origine",
      render: (row) => (
        <Badge status={COMPLAINT_ORIGIN_BADGE[row.origin]}>{COMPLAINT_ORIGIN_LABEL[row.origin]}</Badge>
      ),
    },
    {
      key: "statut",
      header: "Statut",
      render: (row) => <Badge status={COMPLAINT_STATUT_BADGE[row.statut]}>{COMPLAINT_STATUT_LABEL[row.statut]}</Badge>,
    },
    { key: "patient", header: "Patient", render: (row) => patientLabel(patientsDirectory, row.patient_id) },
    {
      key: "gestionnaire",
      header: "Gestionnaire",
      render: (row) => gestionnaireLabel(usersDirectory, row.gestionnaire_id, row.gestionnaire_label),
    },
    { key: "created_at", header: "Créée le", render: (row) => formatDate(row.created_at) },
  ];

  return (
    <Card>
      <CardHeader>
        <div>
          <CardTitle>Réclamations</CardTitle>
        </div>
        <div className="flex items-center gap-2">
          <Select
            value={statutFilter}
            onChange={(e) => setStatutFilter(e.target.value as ComplaintStatut | "")}
            className="w-40"
            aria-label="Filtrer par statut"
          >
            <option value="">Toutes</option>
            {COMPLAINT_STATUT_VALUES.map((value) => (
              <option key={value} value={value}>
                {COMPLAINT_STATUT_LABEL[value]}
              </option>
            ))}
          </Select>
          {canCreate && (
            <Button size="sm" onClick={() => setCreateOpen(true)}>
              <Plus size={14} />
              Nouvelle réclamation
            </Button>
          )}
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        {complaintsQuery.isError ? (
          <ErrorState message={apiErrorMessage(complaintsQuery.error)} onRetry={() => complaintsQuery.refetch()} />
        ) : (
          <DataTable
            columns={columns}
            data={complaintsQuery.data?.data ?? []}
            rowKey={(row) => row.id}
            isLoading={complaintsQuery.isLoading}
            onRowClick={(row) => setSelectedComplaintId(row.id)}
            emptyState={
              <EmptyState
                icon={MessageSquareWarning}
                title="Aucune réclamation"
                description="Aucune réclamation n'est visible pour vous actuellement."
                actionLabel={canCreate ? "Nouvelle réclamation" : undefined}
                onAction={canCreate ? () => setCreateOpen(true) : undefined}
              />
            }
          />
        )}

        {selectedComplaintId && (
          <ComplaintDetail
            complaintId={selectedComplaintId}
            onClose={() => setSelectedComplaintId(null)}
            patientsDirectory={patientsDirectory}
            usersDirectory={usersDirectory}
          />
        )}
      </CardContent>

      <CreateComplaintDialog open={createOpen} onOpenChange={setCreateOpen} />
    </Card>
  );
}

interface ComplaintDetailProps {
  complaintId: number;
  onClose: () => void;
  patientsDirectory: PatientsDirectory;
  usersDirectory: UsersDirectory;
}

/**
 * Détail d'une réclamation, patron exact de PurchaseOrderDetail
 * (achats-page.tsx) : ses propres branches loading/error, boutons d'action
 * gardés par permission + statut (commodité UX uniquement — le backend reste
 * l'arbitre final via ses abort_if(), tout message d'erreur est affiché
 * verbatim, jamais reformulé), encart d'erreur standard, historique en liste
 * divide-y.
 */
function ComplaintDetail({ complaintId, onClose, patientsDirectory, usersDirectory }: ComplaintDetailProps) {
  const { user, hasPermission } = useAuth();
  const complaintQuery = useComplaint(complaintId);

  const [actionError, setActionError] = useState<string | null>(null);
  const [assigning, setAssigning] = useState(false);
  const [selectedGestionnaireId, setSelectedGestionnaireId] = useState<number | "">("");
  const [responding, setResponding] = useState(false);
  const [responseMessage, setResponseMessage] = useState("");
  const [responseVisiblePatient, setResponseVisiblePatient] = useState(true);

  const assignComplaint = useAssignComplaint();
  const respondToComplaint = useRespondToComplaint();
  const resolveComplaint = useResolveComplaint();
  const closeComplaint = useCloseComplaint();

  if (complaintQuery.isLoading) {
    return (
      <div className="rounded-md border border-border p-4">
        <Skeleton className="h-5 w-48" />
        <Skeleton className="mt-3 h-24 w-full" />
      </div>
    );
  }

  if (complaintQuery.isError) {
    return <ErrorState message={apiErrorMessage(complaintQuery.error)} onRetry={() => complaintQuery.refetch()} />;
  }

  const complaint = complaintQuery.data;
  if (!complaint) return null;

  // Commodité d'affichage uniquement : le backend (assertCanManage()) reste
  // le seul arbitre — un utilisateur sans manage_all et non assigné qui
  // parviendrait malgré tout à déclencher une action se verrait renvoyer le
  // 403 "Vous ne pouvez agir que sur les réclamations qui vous sont
  // assignées." affiché verbatim via apiErrorMessage(err).
  const isOwner = complaint.gestionnaire_id === user?.id || hasPermission("reclamations.manage_all");
  const canAssign = complaint.statut === "ouverte" && hasPermission("reclamations.update");
  const canRespond = isOwner;
  const canResolve = complaint.statut === "en_cours" && isOwner;
  const canClose = complaint.statut === "resolue" && isOwner;
  // GET /users (useUsersDirectory) est gardé côté backend par users.view —
  // un détenteur de reclamations.update sans users.view (ex. secretaire, le
  // rôle "gestionnaire" archétypal de ce workflow) ne peut donc jamais
  // peupler un sélecteur listant tout le personnel. L'auto-assignation ne
  // nécessite aucune lecture de ce répertoire (gestionnaire_id = son propre
  // id, déjà connu via useAuth()) : elle reste donc toujours disponible pour
  // quiconque a reclamations.update, le picker complet n'étant proposé en
  // plus que lorsque le répertoire est effectivement chargé (users.view).
  const canPickAnyAssignee = (usersDirectory.data?.length ?? 0) > 0;

  function handleAssignSelf() {
    if (!user) return;
    setActionError(null);
    assignComplaint.mutate(
      { id: complaintId, gestionnaireId: user.id },
      { onError: (err) => setActionError(apiErrorMessage(err)) },
    );
  }

  function handleAssignConfirm() {
    if (!selectedGestionnaireId) return;
    setActionError(null);
    assignComplaint.mutate(
      { id: complaintId, gestionnaireId: Number(selectedGestionnaireId) },
      {
        onSuccess: () => {
          setAssigning(false);
          setSelectedGestionnaireId("");
        },
        onError: (err) => setActionError(apiErrorMessage(err)),
      },
    );
  }

  function handleRespondSend() {
    if (!responseMessage.trim()) return;
    setActionError(null);
    respondToComplaint.mutate(
      { id: complaintId, message: responseMessage.trim(), visiblePatient: responseVisiblePatient },
      {
        onSuccess: () => {
          setResponding(false);
          setResponseMessage("");
          setResponseVisiblePatient(true);
        },
        onError: (err) => setActionError(apiErrorMessage(err)),
      },
    );
  }

  function handleResolve() {
    setActionError(null);
    resolveComplaint.mutate(complaintId, { onError: (err) => setActionError(apiErrorMessage(err)) });
  }

  function handleClose() {
    setActionError(null);
    closeComplaint.mutate(complaintId, { onError: (err) => setActionError(apiErrorMessage(err)) });
  }

  const responses = complaint.responses ?? [];

  return (
    <div className="space-y-4 rounded-md border border-border p-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-text">
            #{complaint.id} · {complaint.motif}
          </p>
          <p className="text-xs text-text-muted">
            {patientLabel(patientsDirectory, complaint.patient_id)} ·{" "}
            {gestionnaireLabel(usersDirectory, complaint.gestionnaire_id, complaint.gestionnaire_label)}
            {complaint.service_concerne ? ` · ${complaint.service_concerne}` : ""}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Badge status={COMPLAINT_ORIGIN_BADGE[complaint.origin]}>{COMPLAINT_ORIGIN_LABEL[complaint.origin]}</Badge>
          <Badge status={COMPLAINT_STATUT_BADGE[complaint.statut]}>{COMPLAINT_STATUT_LABEL[complaint.statut]}</Badge>
          <Button variant="ghost" size="sm" onClick={onClose}>
            Fermer
          </Button>
        </div>
      </div>

      <div>
        <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Description</p>
        <p className="mt-1 whitespace-pre-wrap text-sm text-text">{complaint.description}</p>
      </div>

      <p className="text-xs text-text-muted">
        Créée le {formatDateTime(complaint.created_at)}
        {complaint.resolved_at && (
          <>
            {" "}
            · Résolue le {formatDateTime(complaint.resolved_at)}
            {complaint.resolved_by_label ? ` par ${complaint.resolved_by_label}` : ""}
          </>
        )}
        {complaint.closed_at && (
          <>
            {" "}
            · Clôturée le {formatDateTime(complaint.closed_at)}
            {complaint.closed_by_label ? ` par ${complaint.closed_by_label}` : ""}
          </>
        )}
      </p>

      <div className="flex flex-wrap items-start gap-2">
        {canAssign && !assigning && (
          <>
            <Button
              size="sm"
              variant="secondary"
              onClick={handleAssignSelf}
              disabled={assignComplaint.isPending}
            >
              {assignComplaint.isPending && <LoaderCircle size={14} className="animate-spin" />}
              M'assigner
            </Button>
            {canPickAnyAssignee && (
              <Button size="sm" variant="ghost" onClick={() => setAssigning(true)}>
                Assigner à un autre utilisateur
              </Button>
            )}
          </>
        )}
        {canAssign && assigning && (
          <div className="flex items-center gap-2">
            <Select
              value={selectedGestionnaireId}
              onChange={(e) => setSelectedGestionnaireId(e.target.value ? Number(e.target.value) : "")}
              className="w-56"
              aria-label="Choisir un gestionnaire"
            >
              <option value="">Choisir un utilisateur…</option>
              {(usersDirectory.data ?? []).map((u) => (
                <option key={u.id} value={u.id}>
                  {u.first_name} {u.last_name}
                </option>
              ))}
            </Select>
            <Button
              size="sm"
              onClick={handleAssignConfirm}
              disabled={!selectedGestionnaireId || assignComplaint.isPending}
            >
              {assignComplaint.isPending && <LoaderCircle size={14} className="animate-spin" />}
              Confirmer
            </Button>
            <Button size="sm" variant="ghost" onClick={() => setAssigning(false)}>
              Annuler
            </Button>
          </div>
        )}

        {canRespond && !responding && (
          <Button size="sm" variant="secondary" onClick={() => setResponding(true)}>
            Répondre
          </Button>
        )}

        {canResolve && (
          <Button size="sm" onClick={handleResolve} disabled={resolveComplaint.isPending}>
            {resolveComplaint.isPending && <LoaderCircle size={14} className="animate-spin" />}
            Résoudre
          </Button>
        )}

        {canClose && (
          <Button size="sm" onClick={handleClose} disabled={closeComplaint.isPending}>
            {closeComplaint.isPending && <LoaderCircle size={14} className="animate-spin" />}
            Clôturer
          </Button>
        )}
      </div>

      {canRespond && responding && (
        <div className="space-y-2">
          <Textarea
            rows={3}
            placeholder="Écrire une réponse…"
            value={responseMessage}
            onChange={(e) => setResponseMessage(e.target.value)}
          />
          <label className="flex items-center gap-2 text-xs text-text-muted">
            <input
              type="checkbox"
              className="h-4 w-4 rounded border-border-strong accent-accent"
              checked={!responseVisiblePatient}
              onChange={(e) => setResponseVisiblePatient(!e.target.checked)}
            />
            Note interne (non visible par le patient)
          </label>
          <div className="flex justify-end gap-2">
            <Button size="sm" variant="ghost" onClick={() => setResponding(false)}>
              Annuler
            </Button>
            <Button
              size="sm"
              onClick={handleRespondSend}
              disabled={!responseMessage.trim() || respondToComplaint.isPending}
            >
              {respondToComplaint.isPending && <LoaderCircle size={14} className="animate-spin" />}
              Envoyer
            </Button>
          </div>
        </div>
      )}

      {actionError && (
        <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
          {actionError}
        </p>
      )}

      <div className="space-y-2">
        <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Historique des réponses</p>
        {responses.length === 0 ? (
          <p className="text-xs text-text-muted">Aucune réponse pour le moment.</p>
        ) : (
          <div className="divide-y divide-border rounded-md border border-border">
            {responses.map((response) => (
              <div key={response.id} className="px-3 py-2 text-sm">
                <div className="flex items-center gap-2">
                  <p className="text-text">{response.message}</p>
                  {!response.visible_patient && <Badge status="neutral">Interne</Badge>}
                </div>
                <p className="mt-1 text-xs text-text-muted">
                  {gestionnaireLabel(usersDirectory, response.auteur_id, response.auteur_label)} ·{" "}
                  {formatDateTime(response.created_at)}
                </p>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
