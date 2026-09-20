import { LoaderCircle, ShieldAlert } from "lucide-react";
import { useState } from "react";
import { Link } from "react-router-dom";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/loading-state";
import { ErrorState } from "@/components/ui/error-state";
import { Textarea } from "@/components/ui/textarea";
import { useAuth } from "@/hooks/use-auth";
import { usePractitioners } from "@/hooks/use-practitioners";
import {
  useAcceptPatientReferral,
  useCompletePatientReferral,
  usePatientReferral,
  usePatientReferralResume,
  useRefusePatientReferral,
} from "@/hooks/use-patient-referrals";
import { usePatientsDirectory } from "@/hooks/use-patients-directory";
import { useStructuresDirectory } from "@/hooks/use-structures-directory";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate, formatDateTime } from "@/lib/datetime";
import { REFERRAL_STATUT_BADGE, REFERRAL_STATUT_LABEL } from "@/pages/referencement/referencement-status";

export interface ReferralDetailProps {
  referralId: number;
  onClose?: () => void;
}

/**
 * Patron ComplaintDetail (complaints-section.tsx) : ses propres branches
 * loading/error, boutons d'action gardés par permission + statut (confort
 * d'affichage seulement — le backend, via abort_unless(destination), reste
 * l'arbitre final ; tout 403 raced affiche verbatim "Seule la structure
 * destinataire peut effectuer cette action.").
 */
export function ReferralDetail({ referralId, onClose }: ReferralDetailProps) {
  const { user, hasPermission } = useAuth();
  const referralQuery = usePatientReferral(referralId);

  const [actionError, setActionError] = useState<string | null>(null);
  const [completing, setCompleting] = useState(false);
  const [compteRenduRetour, setCompteRenduRetour] = useState("");

  const acceptReferral = useAcceptPatientReferral();
  const refuseReferral = useRefusePatientReferral();
  const completeReferral = useCompletePatientReferral();

  const structuresDirectory = useStructuresDirectory(hasPermission("referrals.create"));
  const patientsDirectory = usePatientsDirectory();
  const practitioners = usePractitioners();

  const referral = referralQuery.data;
  const isOrigin = referral ? referral.structure_origine_id === user?.structure_id : false;

  // Résumé minimal cross-structure : seulement activé quand nous sommes la
  // structure destinataire (accès à un patient qui n'est pas le nôtre).
  const resumeQuery = usePatientReferralResume(referralId, Boolean(referral) && !isOrigin);

  if (referralQuery.isLoading) {
    return (
      <div className="rounded-md border border-border p-4">
        <Skeleton className="h-5 w-48" />
        <Skeleton className="mt-3 h-24 w-full" />
      </div>
    );
  }

  if (referralQuery.isError) {
    return <ErrorState message={apiErrorMessage(referralQuery.error)} onRetry={() => referralQuery.refetch()} />;
  }

  if (!referral) return null;

  function structureLabel(structureId: number): string {
    if (structureId === user?.structure_id) return "Notre structure";
    const entry = (structuresDirectory.data ?? []).find((s) => s.id === structureId);
    if (!entry) return `Structure #${structureId}`;
    return entry.legal_name || entry.trade_name || entry.code;
  }

  function praticienLabel(): string {
    if (!isOrigin) return `Praticien #${referral!.praticien_referent_id}`;
    const practitioner = (practitioners.data ?? []).find((p) => p.id === referral!.praticien_referent_id);
    return practitioner ? `Dr ${practitioner.first_name} ${practitioner.last_name}` : `Praticien #${referral!.praticien_referent_id}`;
  }

  const isDestination = user?.structure_id === referral.structure_destination_id;
  const canAccept = referral.statut === "envoye" && isDestination && hasPermission("referrals.accept");
  const canRefuse = referral.statut === "envoye" && isDestination && hasPermission("referrals.refuse");
  const canComplete = referral.statut === "accepte" && isDestination && hasPermission("referrals.update");

  const isPending = acceptReferral.isPending || refuseReferral.isPending || completeReferral.isPending;

  function handleAccept() {
    setActionError(null);
    acceptReferral.mutate(referralId, { onError: (err) => setActionError(apiErrorMessage(err)) });
  }

  function handleRefuse() {
    setActionError(null);
    refuseReferral.mutate(referralId, { onError: (err) => setActionError(apiErrorMessage(err)) });
  }

  function handleCompleteSubmit() {
    if (!compteRenduRetour.trim()) return;
    setActionError(null);
    completeReferral.mutate(
      { id: referralId, compte_rendu_retour: compteRenduRetour.trim() },
      {
        onSuccess: () => {
          setCompleting(false);
          setCompteRenduRetour("");
        },
        onError: (err) => setActionError(apiErrorMessage(err)),
      },
    );
  }

  const patient = isOrigin ? patientsDirectory.byId.get(referral.patient_id) : null;

  return (
    <div className="space-y-4 rounded-md border border-border p-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-text">Référencement #{referral.id}</p>
          <p className="text-xs text-text-muted">
            {structureLabel(referral.structure_origine_id)} → {structureLabel(referral.structure_destination_id)}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Badge status={REFERRAL_STATUT_BADGE[referral.statut]}>{REFERRAL_STATUT_LABEL[referral.statut]}</Badge>
          {onClose && (
            <Button variant="ghost" size="sm" onClick={onClose}>
              Fermer
            </Button>
          )}
        </div>
      </div>

      <div className="flex items-start gap-2 rounded-md border border-warning/30 bg-warning/5 px-3 py-2">
        <ShieldAlert size={16} className="mt-0.5 shrink-0 text-warning" />
        <p className="text-xs text-text">
          Enregistrement partagé entre deux structures — accès exceptionnel, journalisé côté serveur.
        </p>
      </div>

      <div>
        <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Patient</p>
        {isOrigin ? (
          patient ? (
            <p className="mt-1 text-sm text-text">
              <Link to={`/patients/${patient.id}`} className="text-accent-light hover:underline">
                {patient.first_name} {patient.last_name}
              </Link>{" "}
              <span className="font-tabular text-xs text-text-subtle">{patient.patient_number}</span>
            </p>
          ) : (
            <p className="mt-1 text-sm text-text">Patient #{referral.patient_id}</p>
          )
        ) : resumeQuery.isLoading ? (
          <Skeleton className="mt-1 h-4 w-40" />
        ) : resumeQuery.isError ? (
          <p className="mt-1 text-xs text-danger">{apiErrorMessage(resumeQuery.error)}</p>
        ) : resumeQuery.data ? (
          <div className="mt-1 space-y-0.5 text-sm text-text">
            <p>
              {resumeQuery.data.nom} <span className="font-tabular text-xs text-text-subtle">{resumeQuery.data.numero_patient}</span>
            </p>
            <p className="text-xs text-text-muted">Né(e) le {formatDate(resumeQuery.data.date_naissance)}</p>
            <p className="text-xs text-text-subtle">
              Résumé minimal cross-structure — aucun accès au dossier complet du patient.
            </p>
          </div>
        ) : (
          <p className="mt-1 text-sm text-text">Patient #{referral.patient_id}</p>
        )}
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Praticien référent</p>
          <p className="mt-1 text-sm text-text">{praticienLabel()}</p>
        </div>
        <div>
          <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Créé le</p>
          <p className="mt-1 text-sm text-text">{formatDateTime(referral.created_at)}</p>
        </div>
      </div>

      <div>
        <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Motif</p>
        <p className="mt-1 whitespace-pre-wrap text-sm text-text">{referral.motif}</p>
      </div>

      {referral.statut === "complete" && referral.compte_rendu_retour && (
        <div>
          <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">
            {isOrigin ? "Contre-référence reçue" : "Compte rendu retour"}
          </p>
          <p className="mt-1 whitespace-pre-wrap text-sm text-text">{referral.compte_rendu_retour}</p>
        </div>
      )}

      <div className="flex flex-wrap items-start gap-2">
        {canAccept && (
          <Button size="sm" onClick={handleAccept} disabled={isPending}>
            {acceptReferral.isPending && <LoaderCircle size={14} className="animate-spin" />}
            Accepter
          </Button>
        )}
        {canRefuse && (
          <Button size="sm" variant="secondary" onClick={handleRefuse} disabled={isPending}>
            {refuseReferral.isPending && <LoaderCircle size={14} className="animate-spin" />}
            Refuser
          </Button>
        )}
        {canComplete && !completing && (
          <Button size="sm" onClick={() => setCompleting(true)}>
            Contre-référence
          </Button>
        )}
      </div>

      {canComplete && completing && (
        <div className="space-y-2">
          <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Compte rendu retour</p>
          <Textarea
            rows={4}
            placeholder="Compte rendu de la contre-référence…"
            value={compteRenduRetour}
            onChange={(e) => setCompteRenduRetour(e.target.value)}
          />
          <div className="flex justify-end gap-2">
            <Button size="sm" variant="ghost" onClick={() => setCompleting(false)}>
              Annuler
            </Button>
            <Button
              size="sm"
              onClick={handleCompleteSubmit}
              disabled={!compteRenduRetour.trim() || completeReferral.isPending}
            >
              {completeReferral.isPending && <LoaderCircle size={14} className="animate-spin" />}
              Envoyer
            </Button>
          </div>
        </div>
      )}

      {actionError && (
        <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{actionError}</p>
      )}
    </div>
  );
}
