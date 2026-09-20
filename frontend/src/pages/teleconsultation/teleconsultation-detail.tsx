import { LoaderCircle } from "lucide-react";
import { useState } from "react";
import { CloseTeleconsultationForm } from "@/components/teleconsultation/close-teleconsultation-form";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ErrorState } from "@/components/ui/error-state";
import { Skeleton } from "@/components/ui/loading-state";
import { useAuth } from "@/hooks/use-auth";
import type { usePatientsDirectory } from "@/hooks/use-patients-directory";
import { useSites } from "@/hooks/use-sites";
import {
  useCancelTeleconsultation,
  useStartTeleconsultation,
  useTeleconsultation,
} from "@/hooks/use-teleconsultations";
import type { useUsersDirectory } from "@/hooks/use-users-directory";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDateTime } from "@/lib/datetime";
import { TELECONSULTATION_STATUT_BADGE, TELECONSULTATION_STATUT_LABEL } from "@/pages/teleconsultation/teleconsultation-status";

type PatientsDirectory = ReturnType<typeof usePatientsDirectory>;
type UsersDirectory = ReturnType<typeof useUsersDirectory>;

function patientLabel(directory: PatientsDirectory, patientId: number): string {
  const patient = directory.byId.get(patientId);
  return patient ? `${patient.first_name} ${patient.last_name}` : `Patient #${patientId}`;
}

function practitionerLabel(directory: UsersDirectory, practitionerId: number): string {
  const user = directory.byId.get(practitionerId);
  return user ? `${user.first_name} ${user.last_name}` : `Praticien #${practitionerId}`;
}

export interface TeleconsultationDetailProps {
  teleconsultationId: number;
  onClose: () => void;
  patientsDirectory: PatientsDirectory;
  usersDirectory: UsersDirectory;
}

/**
 * Détail d'une téléconsultation — patron exact de ComplaintDetail
 * (pages/qualite/complaints-section.tsx) : ses propres branches
 * loading/error, boutons d'action gardés par permission + statut (commodité
 * UX uniquement — le backend reste l'arbitre final via ses abort_if() dans
 * TeleconsultationController, tout message d'erreur est affiché verbatim,
 * jamais reformulé).
 */
export function TeleconsultationDetail({
  teleconsultationId,
  onClose,
  patientsDirectory,
  usersDirectory,
}: TeleconsultationDetailProps) {
  const { hasPermission } = useAuth();
  const teleconsultationQuery = useTeleconsultation(teleconsultationId);
  // sites.view n'est pas accordé au rôle médecin (cf. RolePermissionSeeder) —
  // sans ce garde, un médecin ouvrant ce détail déclenche un 403 inutile sur
  // GET /sites (contrat documenté dans use-sites.ts).
  const sites = useSites(hasPermission("sites.view"));

  const [actionError, setActionError] = useState<string | null>(null);
  const [closing, setClosing] = useState(false);

  const startTeleconsultation = useStartTeleconsultation();
  const cancelTeleconsultation = useCancelTeleconsultation();

  if (teleconsultationQuery.isLoading) {
    return (
      <div className="rounded-md border border-border p-4">
        <Skeleton className="h-5 w-48" />
        <Skeleton className="mt-3 h-24 w-full" />
      </div>
    );
  }

  if (teleconsultationQuery.isError) {
    return (
      <ErrorState
        message={apiErrorMessage(teleconsultationQuery.error)}
        onRetry={() => teleconsultationQuery.refetch()}
      />
    );
  }

  const teleconsultation = teleconsultationQuery.data;
  if (!teleconsultation) return null;

  const site = sites.data?.find((s) => s.id === teleconsultation.site_id);
  const siteLabel = site ? site.name : `Site #${teleconsultation.site_id}`;

  // Commodité d'affichage uniquement : le backend (abort_if() de
  // TeleconsultationController::start/close/cancel) reste le seul arbitre —
  // un appel racé sur une téléconsultation déjà clôturée/annulée afficherait
  // le message 422 verbatim via apiErrorMessage(err) ci-dessous.
  const canStart = teleconsultation.statut === "planifiee" && hasPermission("teleconsultation.update");
  const canClose =
    (teleconsultation.statut === "planifiee" || teleconsultation.statut === "en_cours") &&
    hasPermission("teleconsultation.update");
  const canCancel = teleconsultation.statut !== "terminee" && hasPermission("teleconsultation.cancel");

  function handleStart() {
    setActionError(null);
    startTeleconsultation.mutate(teleconsultationId, {
      onError: (err) => setActionError(apiErrorMessage(err)),
    });
  }

  function handleCancel() {
    setActionError(null);
    cancelTeleconsultation.mutate(teleconsultationId, {
      onError: (err) => setActionError(apiErrorMessage(err)),
    });
  }

  return (
    <div className="space-y-4 rounded-md border border-border p-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-text">
            Téléconsultation #{teleconsultation.id}
          </p>
          <p className="text-xs text-text-muted">
            {patientLabel(patientsDirectory, teleconsultation.patient_id)} ·{" "}
            {practitionerLabel(usersDirectory, teleconsultation.practitioner_id)} · {siteLabel}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Badge status={TELECONSULTATION_STATUT_BADGE[teleconsultation.statut]}>
            {TELECONSULTATION_STATUT_LABEL[teleconsultation.statut]}
          </Badge>
          <Button variant="ghost" size="sm" onClick={onClose}>
            Fermer
          </Button>
        </div>
      </div>

      <p className="text-xs text-text-muted">
        Planifiée le {formatDateTime(teleconsultation.created_at)}
        {teleconsultation.started_at && <> · Démarrée le {formatDateTime(teleconsultation.started_at)}</>}
        {teleconsultation.ended_at && <> · Clôturée le {formatDateTime(teleconsultation.ended_at)}</>}
      </p>

      <div>
        <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Lien de session</p>
        {teleconsultation.lien_session ? (
          <>
            <p className="mt-1 whitespace-pre-wrap text-sm text-text">{teleconsultation.lien_session}</p>
            <p className="mt-1 text-xs text-text-subtle">
              Lien de session à transmettre manuellement au patient — aucune visioconférence n'est intégrée à
              l'application.
            </p>
          </>
        ) : (
          <p className="mt-1 text-xs text-text-muted">Aucun lien de session renseigné pour l'instant.</p>
        )}
      </div>

      {!closing && (
        <div className="flex flex-wrap items-center gap-2">
          {canStart && (
            <Button size="sm" variant="secondary" onClick={handleStart} disabled={startTeleconsultation.isPending}>
              {startTeleconsultation.isPending && <LoaderCircle size={14} className="animate-spin" />}
              Démarrer
            </Button>
          )}
          {canClose && (
            <Button size="sm" onClick={() => setClosing(true)}>
              Clôturer
            </Button>
          )}
          {canCancel && (
            <Button size="sm" variant="danger" onClick={handleCancel} disabled={cancelTeleconsultation.isPending}>
              {cancelTeleconsultation.isPending && <LoaderCircle size={14} className="animate-spin" />}
              Annuler
            </Button>
          )}
        </div>
      )}

      {actionError && (
        <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
          {actionError}
        </p>
      )}

      {closing && canClose && (
        <CloseTeleconsultationForm teleconsultationId={teleconsultationId} onCancel={() => setClosing(false)} />
      )}
    </div>
  );
}
