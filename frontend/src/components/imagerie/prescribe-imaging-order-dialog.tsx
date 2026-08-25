import { CheckCircle2, LoaderCircle } from "lucide-react";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { useCreateImagingOrder } from "@/hooks/use-imaging-orders";
import { apiErrorMessage } from "@/lib/api-error";
import { EXAM_TYPE_LABEL } from "@/pages/imagerie/imaging-status";
import type { ImagingExamType } from "@/types/api";

const EXAM_TYPES: ImagingExamType[] = ["radio", "echo", "scanner", "irm"];

export interface PrescribeImagingOrderDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  patientId: number;
  siteId: number | null;
  practitionerId: number;
  consultationId?: number | null;
  onCreated?: () => void;
}

/**
 * "Prescrire un examen d'imagerie" dialog. Submits the order exactly as composed
 * here — the backend is the sole authority on whether the request is valid; any
 * 422/403 is surfaced verbatim via apiErrorMessage rather than pre-validated
 * client-side.
 */
export function PrescribeImagingOrderDialog({
  open,
  onOpenChange,
  patientId,
  siteId,
  practitionerId,
  consultationId = null,
  onCreated,
}: PrescribeImagingOrderDialogProps) {
  const [examType, setExamType] = useState<ImagingExamType>("radio");
  const [notes, setNotes] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [createdOrderId, setCreatedOrderId] = useState<number | null>(null);

  const createImagingOrder = useCreateImagingOrder();

  useEffect(() => {
    if (!open) {
      setExamType("radio");
      setNotes("");
      setError(null);
      setCreatedOrderId(null);
    }
  }, [open]);

  function handleSubmit() {
    if (!siteId) return;
    setError(null);
    createImagingOrder.mutate(
      {
        site_id: siteId,
        patient_id: patientId,
        prescriber_id: practitionerId,
        consultation_id: consultationId,
        exam_type: examType,
        notes: notes.trim() || undefined,
      },
      {
        onSuccess: (order) => {
          setCreatedOrderId(order.id);
          onCreated?.();
        },
        onError: (err) => setError(apiErrorMessage(err)),
      },
    );
  }

  const canSubmit = Boolean(siteId);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Prescrire un examen d'imagerie</DialogTitle>
          <DialogDescription>Sélectionnez le type d'examen à prescrire pour ce patient.</DialogDescription>
        </DialogHeader>

        {createdOrderId ? (
          <div className="space-y-4 py-4 text-center">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-success/10 text-success">
              <CheckCircle2 size={24} />
            </div>
            <p className="text-sm text-text">Demande créée — #{createdOrderId}</p>
          </div>
        ) : (
          <div className="space-y-4">
            {!siteId && (
              <p className="rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-xs text-warning">
                Aucun site associé à cette consultation — impossible de prescrire.
              </p>
            )}

            <div>
              <Label>Type d'examen</Label>
              <Select value={examType} onChange={(e) => setExamType(e.target.value as ImagingExamType)}>
                {EXAM_TYPES.map((type) => (
                  <option key={type} value={type}>
                    {EXAM_TYPE_LABEL[type]}
                  </option>
                ))}
              </Select>
            </div>

            <div className="space-y-1.5">
              <label htmlFor="imaging-order-notes" className="text-xs font-medium text-text-muted">
                Motif
              </label>
              <textarea
                id="imaging-order-notes"
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                rows={3}
                className="w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                placeholder="Contexte clinique, motif de la demande..."
              />
            </div>

            {error && (
              <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
                {error}
              </p>
            )}
          </div>
        )}

        <DialogFooter>
          {createdOrderId ? (
            <Button onClick={() => onOpenChange(false)}>Fermer</Button>
          ) : (
            <>
              <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={createImagingOrder.isPending}>
                Annuler
              </Button>
              <Button onClick={handleSubmit} disabled={!canSubmit || createImagingOrder.isPending}>
                {createImagingOrder.isPending && <LoaderCircle size={16} className="animate-spin" />}
                Créer la demande
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
