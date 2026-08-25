import { CheckCircle2, LoaderCircle } from "lucide-react";
import { useEffect, useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { usePlanSurgicalProcedure } from "@/hooks/use-surgical-procedures";
import { apiErrorMessage } from "@/lib/api-error";

export interface PlanSurgicalProcedureDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  patientId?: number;
  siteId: number | null;
  hospitalizationId?: number | null;
  defaultSurgeonId?: number;
  defaultAnesthesiologistId?: number;
  onPlanned?: () => void;
}

function toDatetimeLocalValue(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/**
 * Formulaire de planification d'une intervention. Aucune règle métier
 * n'est vérifiée côté client au-delà des champs requis — le backend est
 * seul juge de la validité de la planification (existence des IDs, etc.).
 */
export function PlanSurgicalProcedureDialog({
  open,
  onOpenChange,
  patientId,
  siteId,
  hospitalizationId = null,
  defaultSurgeonId,
  defaultAnesthesiologistId,
  onPlanned,
}: PlanSurgicalProcedureDialogProps) {
  const [patientIdInput, setPatientIdInput] = useState(patientId ? String(patientId) : "");
  const [surgeonId, setSurgeonId] = useState(defaultSurgeonId ? String(defaultSurgeonId) : "");
  const [anesthesiologistId, setAnesthesiologistId] = useState(
    defaultAnesthesiologistId ? String(defaultAnesthesiologistId) : "",
  );
  const [operatingRoom, setOperatingRoom] = useState("");
  const [procedureType, setProcedureType] = useState("");
  const [scheduledAt, setScheduledAt] = useState(() => toDatetimeLocalValue(new Date()));
  const [error, setError] = useState<string | null>(null);
  const [plannedId, setPlannedId] = useState<number | null>(null);

  const planProcedure = usePlanSurgicalProcedure();

  useEffect(() => {
    if (!open) {
      setPatientIdInput(patientId ? String(patientId) : "");
      setSurgeonId(defaultSurgeonId ? String(defaultSurgeonId) : "");
      setAnesthesiologistId(defaultAnesthesiologistId ? String(defaultAnesthesiologistId) : "");
      setOperatingRoom("");
      setProcedureType("");
      setScheduledAt(toDatetimeLocalValue(new Date()));
      setError(null);
      setPlannedId(null);
    }
  }, [open, patientId, defaultSurgeonId, defaultAnesthesiologistId]);

  const resolvedPatientId = patientId ?? (patientIdInput.trim() ? Number(patientIdInput) : null);

  function handleSubmit() {
    if (
      !siteId ||
      !resolvedPatientId ||
      !surgeonId.trim() ||
      !anesthesiologistId.trim() ||
      !operatingRoom.trim() ||
      !procedureType.trim() ||
      !scheduledAt
    ) {
      return;
    }
    setError(null);
    planProcedure.mutate(
      {
        site_id: siteId,
        patient_id: resolvedPatientId,
        hospitalization_id: hospitalizationId,
        surgeon_id: Number(surgeonId),
        anesthesiologist_id: Number(anesthesiologistId),
        operating_room: operatingRoom.trim(),
        procedure_type: procedureType.trim(),
        scheduled_at: new Date(scheduledAt).toISOString(),
      },
      {
        onSuccess: (procedure) => {
          setPlannedId(procedure.id);
          onPlanned?.();
        },
        onError: (err) => setError(apiErrorMessage(err)),
      },
    );
  }

  const canSubmit = Boolean(
    siteId &&
      resolvedPatientId &&
      surgeonId.trim() &&
      anesthesiologistId.trim() &&
      operatingRoom.trim() &&
      procedureType.trim() &&
      scheduledAt,
  );

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Planifier une intervention chirurgicale</DialogTitle>
          <DialogDescription>Renseignez l'équipe, la salle et la date planifiée.</DialogDescription>
        </DialogHeader>

        {plannedId ? (
          <div className="space-y-4 py-4 text-center">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-success/10 text-success">
              <CheckCircle2 size={24} />
            </div>
            <p className="text-sm text-text">Intervention planifiée — #{plannedId}</p>
          </div>
        ) : (
          <div className="space-y-4">
            {!siteId && (
              <p className="rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-xs text-warning">
                Aucun site associé — impossible de planifier une intervention.
              </p>
            )}

            {patientId === undefined && (
              <div>
                <Label>ID patient</Label>
                <Input
                  type="number"
                  min={1}
                  value={patientIdInput}
                  onChange={(e) => setPatientIdInput(e.target.value)}
                  placeholder="ex. 42"
                />
              </div>
            )}

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <Label>ID chirurgien</Label>
                <Input type="number" min={1} value={surgeonId} onChange={(e) => setSurgeonId(e.target.value)} />
              </div>
              <div>
                <Label>ID anesthésiste</Label>
                <Input
                  type="number"
                  min={1}
                  value={anesthesiologistId}
                  onChange={(e) => setAnesthesiologistId(e.target.value)}
                />
              </div>
              <div>
                <Label>Salle</Label>
                <Input
                  value={operatingRoom}
                  onChange={(e) => setOperatingRoom(e.target.value)}
                  placeholder="ex. Bloc 2"
                />
              </div>
              <div>
                <Label>Date/heure planifiée</Label>
                <Input type="datetime-local" value={scheduledAt} onChange={(e) => setScheduledAt(e.target.value)} />
              </div>
              <div className="sm:col-span-2">
                <Label>Type d'intervention</Label>
                <Input
                  value={procedureType}
                  onChange={(e) => setProcedureType(e.target.value)}
                  placeholder="ex. Appendicectomie"
                />
              </div>
            </div>

            {error && (
              <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
                {error}
              </p>
            )}
          </div>
        )}

        <DialogFooter>
          {plannedId ? (
            <Button onClick={() => onOpenChange(false)}>Fermer</Button>
          ) : (
            <>
              <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={planProcedure.isPending}>
                Annuler
              </Button>
              <Button onClick={handleSubmit} disabled={!canSubmit || planProcedure.isPending}>
                {planProcedure.isPending && <LoaderCircle size={16} className="animate-spin" />}
                Planifier
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
