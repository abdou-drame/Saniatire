import { CheckCircle2, CircleDashed, LoaderCircle, PlayCircle, XCircle } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { ErrorState } from "@/components/ui/error-state";
import { Skeleton } from "@/components/ui/loading-state";
import { useAuth } from "@/hooks/use-auth";
import {
  useCancelSurgicalProcedure,
  useCompleteSurgicalProcedure,
  useStartSurgicalProcedure,
  useSurgicalProcedure,
  useValidateChecklistStep,
} from "@/hooks/use-surgical-procedures";
import { apiErrorMessage } from "@/lib/api-error";
import { roleLabel } from "@/config/role-labels";
import { formatDateTime } from "@/lib/datetime";
import {
  CHECKLIST_STEP_ITEMS,
  CHECKLIST_STEP_LABEL,
  PROCEDURE_STATUS_BADGE,
  PROCEDURE_STATUS_LABEL,
} from "@/pages/bloc-operatoire/bloc-operatoire-status";
import type { SurgicalChecklistStep } from "@/types/api";

const CHECKLIST_STEPS: SurgicalChecklistStep[] = ["avant_anesthesie", "avant_incision", "avant_sortie_bloc"];

export function SurgicalProcedureDetail({ procedureId, onClose }: { procedureId: number; onClose: () => void }) {
  const { hasPermission } = useAuth();
  const procedureQuery = useSurgicalProcedure(procedureId);

  const startProcedure = useStartSurgicalProcedure();
  const completeProcedure = useCompleteSurgicalProcedure();
  const cancelProcedure = useCancelSurgicalProcedure();
  const validateStep = useValidateChecklistStep();

  const [completeError, setCompleteError] = useState<string | null>(null);
  const [startError, setStartError] = useState<string | null>(null);
  const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
  const [cancelError, setCancelError] = useState<string | null>(null);

  if (procedureQuery.isLoading) {
    return (
      <Card>
        <CardContent className="p-4">
          <Skeleton className="h-5 w-48" />
          <Skeleton className="mt-3 h-32 w-full" />
        </CardContent>
      </Card>
    );
  }

  if (procedureQuery.isError) {
    return (
      <Card>
        <CardContent className="p-4">
          <ErrorState message={apiErrorMessage(procedureQuery.error)} onRetry={() => procedureQuery.refetch()} />
        </CardContent>
      </Card>
    );
  }

  const procedure = procedureQuery.data;
  if (!procedure) return null;

  const canUpdate = hasPermission("bloc_operatoire.update");
  const canCancel = hasPermission("bloc_operatoire.cancel");
  const canValidate = hasPermission("bloc_operatoire.validate");

  function handleStart() {
    setStartError(null);
    startProcedure.mutate(procedureId, { onError: (err) => setStartError(apiErrorMessage(err)) });
  }

  /**
   * Ne jamais désactiver ce bouton en fonction d'un décompte local des
   * étapes de checklist validées : le backend est la seule autorité de
   * sécurité réelle sur cette règle (§6/§7 cahier des charges). S'il
   * refuse (422), le message exact est affiché tel quel ci-dessous.
   */
  function handleComplete() {
    setCompleteError(null);
    completeProcedure.mutate(procedureId, { onError: (err) => setCompleteError(apiErrorMessage(err)) });
  }

  function handleCancel() {
    setCancelError(null);
    cancelProcedure.mutate(procedureId, {
      onSuccess: () => setCancelDialogOpen(false),
      onError: (err) => setCancelError(apiErrorMessage(err)),
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Intervention #{procedure.id}</CardTitle>
        <Button variant="ghost" size="sm" onClick={onClose}>
          Fermer
        </Button>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p className="text-sm font-medium text-text">
              {procedure.patient ? `${procedure.patient.first_name} ${procedure.patient.last_name}` : "—"} ·{" "}
              {procedure.procedure_type}
            </p>
            <p className="text-xs text-text-muted">
              {procedure.surgeon_label ?? "—"} (chirurgien) · {procedure.anesthesiologist_label ?? "—"} (anesthésiste)
              · Salle {procedure.operating_room}
            </p>
            <p className="text-xs text-text-subtle">Planifiée le {formatDateTime(procedure.scheduled_at)}</p>
          </div>
          <Badge status={PROCEDURE_STATUS_BADGE[procedure.status]}>{PROCEDURE_STATUS_LABEL[procedure.status]}</Badge>
        </div>

        <div className="space-y-3">
          <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Checklist chirurgicale</p>
          {CHECKLIST_STEPS.map((step) => {
            const checklist = procedure.checklists.find((c) => c.step === step) ?? null;
            return (
              <ChecklistStepRow
                key={step}
                step={step}
                validatedAt={checklist?.validated_at ?? null}
                validatorLabel={checklist?.validator_label ?? null}
                validatorRole={checklist?.validator_role ?? null}
                canValidate={canValidate && procedure.status === "en_cours"}
                onValidate={(items) =>
                  validateStep.mutateAsync({ surgicalProcedureId: procedureId, step, items })
                }
              />
            );
          })}
        </div>

        {(startError || completeError) && (
          <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
            {startError ?? completeError}
          </p>
        )}

        <div className="flex flex-wrap items-center gap-2">
          {canUpdate && procedure.status === "planifiee" && (
            <Button size="sm" onClick={handleStart} disabled={startProcedure.isPending}>
              {startProcedure.isPending && <LoaderCircle size={14} className="animate-spin" />}
              <PlayCircle size={14} />
              Démarrer l'intervention
            </Button>
          )}

          {canUpdate && procedure.status === "en_cours" && (
            <Button size="sm" onClick={handleComplete} disabled={completeProcedure.isPending}>
              {completeProcedure.isPending && <LoaderCircle size={14} className="animate-spin" />}
              <CheckCircle2 size={14} />
              Terminer l'intervention
            </Button>
          )}

          {canCancel && !["terminee", "annulee"].includes(procedure.status) && (
            <Button variant="secondary" size="sm" onClick={() => setCancelDialogOpen(true)}>
              <XCircle size={14} />
              Annuler l'intervention
            </Button>
          )}
        </div>
      </CardContent>

      <ConfirmDialog
        open={cancelDialogOpen}
        onOpenChange={setCancelDialogOpen}
        title="Annulation de l'intervention"
        description="Confirmer l'annulation de cette intervention chirurgicale."
        confirmLabel="Confirmer l'annulation"
        isPending={cancelProcedure.isPending}
        onConfirm={handleCancel}
      />
      {cancelError && (
        <p className="mx-5 mb-5 rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
          {cancelError}
        </p>
      )}
    </Card>
  );
}

function ChecklistStepRow({
  step,
  validatedAt,
  validatorLabel,
  validatorRole,
  canValidate,
  onValidate,
}: {
  step: SurgicalChecklistStep;
  validatedAt: string | null;
  validatorLabel: string | null;
  validatorRole: string | null;
  canValidate: boolean;
  onValidate: (items: string[]) => Promise<unknown>;
}) {
  const [isPending, setIsPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const items = CHECKLIST_STEP_ITEMS[step];
  const isValidated = validatedAt !== null;

  async function handleValidate() {
    setError(null);
    setIsPending(true);
    try {
      await onValidate(items);
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setIsPending(false);
    }
  }

  return (
    <div className="rounded-md border border-border p-3" data-testid={`checklist-step-${step}`}>
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          {isValidated ? (
            <CheckCircle2 size={16} className="text-success" />
          ) : (
            <CircleDashed size={16} className="text-text-subtle" />
          )}
          <span className="text-sm font-medium text-text">{CHECKLIST_STEP_LABEL[step]}</span>
        </div>
        <Badge status={isValidated ? "success" : "neutral"}>{isValidated ? "Validée" : "Non validée"}</Badge>
      </div>
      <ul className="mt-2 ml-6 list-disc space-y-0.5 text-xs text-text-muted">
        {items.map((item) => (
          <li key={item}>{item}</li>
        ))}
      </ul>
      {isValidated && validatedAt && (
        <p className="mt-2 text-xs text-text-subtle">
          Validée le {formatDateTime(validatedAt)}
          {validatorLabel && (
            <>
              {" "}
              par {validatorLabel}
              {validatorRole ? ` (${roleLabel(validatorRole)})` : ""}
            </>
          )}
        </p>
      )}
      {error && (
        <p className="mt-2 rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{error}</p>
      )}
      {!isValidated && canValidate && (
        <Button size="sm" className="mt-2" onClick={handleValidate} disabled={isPending}>
          {isPending && <LoaderCircle size={14} className="animate-spin" />}
          Valider cette étape
        </Button>
      )}
    </div>
  );
}
