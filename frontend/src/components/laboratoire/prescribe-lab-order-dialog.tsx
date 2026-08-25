import { CheckCircle2, LoaderCircle, Search, X } from "lucide-react";
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
import { Skeleton } from "@/components/ui/loading-state";
import { useCreateLabOrder } from "@/hooks/use-lab-orders";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { useLoincCodeSearch } from "@/hooks/use-loinc-codes";
import { apiErrorMessage } from "@/lib/api-error";
import type { PrescriberLoincCode } from "@/types/api";

export interface PrescribeLabOrderDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  patientId: number;
  siteId: number | null;
  practitionerId: number;
  consultationId?: number | null;
  onCreated?: () => void;
}

/**
 * "Prescrire une analyse" dialog. Submits the order exactly as composed here — the
 * backend is the sole authority on whether the request is valid; any 422/403 is
 * surfaced verbatim via apiErrorMessage rather than pre-validated client-side.
 */
export function PrescribeLabOrderDialog({
  open,
  onOpenChange,
  patientId,
  siteId,
  practitionerId,
  consultationId = null,
  onCreated,
}: PrescribeLabOrderDialogProps) {
  const [search, setSearch] = useState("");
  const [selectedCodes, setSelectedCodes] = useState<PrescriberLoincCode[]>([]);
  const [notes, setNotes] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [createdOrderId, setCreatedOrderId] = useState<number | null>(null);

  const debouncedSearch = useDebouncedValue(search, 300);
  const loincQuery = useLoincCodeSearch(debouncedSearch);
  const createLabOrder = useCreateLabOrder();

  useEffect(() => {
    if (!open) {
      setSearch("");
      setSelectedCodes([]);
      setNotes("");
      setError(null);
      setCreatedOrderId(null);
    }
  }, [open]);

  function toggleCode(code: PrescriberLoincCode) {
    setSelectedCodes((prev) =>
      prev.some((c) => c.id === code.id) ? prev.filter((c) => c.id !== code.id) : [...prev, code],
    );
  }

  function handleSubmit() {
    if (!siteId || selectedCodes.length === 0) return;
    setError(null);
    createLabOrder.mutate(
      {
        site_id: siteId,
        patient_id: patientId,
        prescriber_id: practitionerId,
        consultation_id: consultationId,
        notes: notes.trim() || undefined,
        items: selectedCodes.map((c) => ({ loinc_code_id: c.id })),
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

  const canSubmit = Boolean(siteId) && selectedCodes.length > 0;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Prescrire une analyse</DialogTitle>
          <DialogDescription>
            Recherchez les analyses de laboratoire à prescrire pour ce patient.
          </DialogDescription>
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

            <div className="space-y-1.5">
              <label className="text-xs font-medium text-text-muted">Analyses demandées</label>
              {selectedCodes.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                  {selectedCodes.map((code) => (
                    <span
                      key={code.id}
                      className="flex items-center gap-1.5 rounded-full border border-accent/30 bg-accent/10 px-2.5 py-0.5 text-xs text-accent-light"
                    >
                      {code.label}
                      <button type="button" onClick={() => toggleCode(code)}>
                        <X size={12} />
                      </button>
                    </span>
                  ))}
                </div>
              )}
              <div className="relative">
                <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-text-subtle" />
                <input
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Rechercher une analyse (ex : glycémie, NFS...)"
                  className="h-9 w-full rounded-md border border-border bg-bg pl-8 pr-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                />
              </div>
              {debouncedSearch.trim().length >= 2 && (
                <div className="max-h-48 overflow-y-auto rounded-md border border-border bg-surface">
                  {loincQuery.isLoading ? (
                    <div className="space-y-1 p-2">
                      <Skeleton className="h-8" />
                      <Skeleton className="h-8" />
                    </div>
                  ) : loincQuery.isError ? (
                    <p className="p-3 text-xs text-danger">{apiErrorMessage(loincQuery.error)}</p>
                  ) : (loincQuery.data ?? []).length === 0 ? (
                    <p className="p-3 text-xs text-text-muted">Aucune analyse trouvée.</p>
                  ) : (
                    (loincQuery.data ?? []).map((code) => {
                      const isSelected = selectedCodes.some((c) => c.id === code.id);
                      return (
                        <button
                          key={code.id}
                          type="button"
                          onClick={() => toggleCode(code)}
                          className={`flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-surface-hover ${
                            isSelected ? "bg-accent/5" : ""
                          }`}
                        >
                          <span className="text-text">{code.label}</span>
                          <span className="text-xs text-text-muted">{code.code}</span>
                        </button>
                      );
                    })
                  )}
                </div>
              )}
            </div>

            <div className="space-y-1.5">
              <label htmlFor="lab-order-notes" className="text-xs font-medium text-text-muted">
                Motif
              </label>
              <textarea
                id="lab-order-notes"
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
              <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={createLabOrder.isPending}>
                Annuler
              </Button>
              <Button onClick={handleSubmit} disabled={!canSubmit || createLabOrder.isPending}>
                {createLabOrder.isPending && <LoaderCircle size={16} className="animate-spin" />}
                Créer la demande
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
