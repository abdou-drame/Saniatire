import { CheckCircle2, LoaderCircle } from "lucide-react";
import { useEffect, useState } from "react";
import { PatientPicker } from "@/components/clinical/patient-picker";
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
import { useBillableItems, useCreateInvoice } from "@/hooks/use-invoices";
import { useSites } from "@/hooks/use-sites";
import { apiErrorMessage } from "@/lib/api-error";
import type { Patient } from "@/types/api";

export interface CreateInvoiceDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/**
 * "Nouvelle facture" — choisit un patient, coche parmi ses prestations
 * "à facturer" (BillableItem, qui n'apparaissent que générées automatiquement
 * côté serveur — aucune création manuelle ici), choisit un site, puis
 * soumet billable_item_ids au backend. Le backend est seul décideur du
 * montant total et de la répartition assurance/patient (InsuranceCoverageService) ;
 * le sous-total affiché ici est une somme brute purement informative des
 * montant_total déjà renvoyés par le serveur pour chaque BillableItem — ce
 * n'est jamais une répartition assurance, et ce n'est jamais le total final
 * de la facture (recalculé par le serveur à la création).
 */
export function CreateInvoiceDialog({ open, onOpenChange }: CreateInvoiceDialogProps) {
  const [patient, setPatient] = useState<Patient | null>(null);
  const [siteId, setSiteId] = useState<number | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [created, setCreated] = useState<{ id: number; numero: string } | null>(null);

  const sitesQuery = useSites();
  const billableItemsQuery = useBillableItems({ patient_id: patient?.id, statut: "a_facturer" });
  const unpricedItemsQuery = useBillableItems({ patient_id: patient?.id, statut: "a_tarifer" });
  const createInvoice = useCreateInvoice();

  useEffect(() => {
    if (!open) {
      setPatient(null);
      setSiteId(null);
      setSelectedIds([]);
      setError(null);
      setCreated(null);
    }
  }, [open]);

  useEffect(() => {
    setSelectedIds([]);
  }, [patient?.id]);

  const items = billableItemsQuery.data?.data ?? [];
  const unpricedItems = unpricedItemsQuery.data?.data ?? [];

  function toggleItem(id: number) {
    setSelectedIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
  }

  const rawSubtotal = items
    .filter((item) => selectedIds.includes(item.id))
    .reduce((sum, item) => sum + Number(item.montant_total), 0);

  const canSubmit = Boolean(patient) && Boolean(siteId) && selectedIds.length > 0;

  function handleSubmit() {
    if (!patient || !siteId || selectedIds.length === 0) return;
    setError(null);
    createInvoice.mutate(
      { patient_id: patient.id, site_id: siteId, billable_item_ids: selectedIds },
      {
        onSuccess: (invoice) => setCreated({ id: invoice.id, numero: invoice.numero }),
        onError: (err) => setError(apiErrorMessage(err)),
      },
    );
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>Nouvelle facture</DialogTitle>
          <DialogDescription>
            Sélectionnez un patient, ses prestations en attente de facturation, puis le site.
          </DialogDescription>
        </DialogHeader>

        {created ? (
          <div className="space-y-4 py-4 text-center">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-success/10 text-success">
              <CheckCircle2 size={24} />
            </div>
            <p className="text-sm text-text">
              Facture créée en brouillon — {created.numero} (#{created.id})
            </p>
          </div>
        ) : (
          <div className="space-y-4">
            <div>
              <Label>Patient</Label>
              <PatientPicker value={patient} onChange={setPatient} />
            </div>

            {patient && (
              <div className="space-y-2">
                <Label className="mb-0">Prestations en attente de facturation</Label>
                {billableItemsQuery.isLoading ? (
                  <p className="text-xs text-text-muted">Chargement…</p>
                ) : items.length === 0 ? (
                  <p className="rounded-md border border-dashed border-border px-3 py-4 text-center text-xs text-text-muted">
                    Aucune prestation en attente de facturation pour ce patient.
                  </p>
                ) : (
                  <div className="divide-y divide-border rounded-md border border-border">
                    {items.map((item) => (
                      <label
                        key={item.id}
                        className="flex cursor-pointer items-center justify-between gap-3 px-3 py-2 text-sm hover:bg-surface-hover"
                      >
                        <div className="flex items-center gap-3">
                          <input
                            type="checkbox"
                            checked={selectedIds.includes(item.id)}
                            onChange={() => toggleItem(item.id)}
                            className="h-4 w-4 rounded border-border-strong"
                          />
                          <div>
                            <p className="text-text">{item.libelle}</p>
                            <p className="text-xs text-text-muted">{item.categorie}</p>
                          </div>
                        </div>
                        <span className="font-tabular text-text">
                          {Number(item.montant_total).toLocaleString("fr-FR")}
                        </span>
                      </label>
                    ))}
                  </div>
                )}

                {items.length > 0 && (
                  <p className="rounded-md border border-border-strong/50 bg-surface-hover/50 px-3 py-2 text-xs text-text-muted">
                    Total brut des prestations sélectionnées (hors répartition assurance, calculée par le serveur à
                    la création) : <span className="font-tabular font-medium text-text">{rawSubtotal.toLocaleString("fr-FR")}</span>
                  </p>
                )}

                {unpricedItems.length > 0 && (
                  <p className="rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-xs text-warning">
                    {unpricedItems.length} prestation{unpricedItems.length > 1 ? "s" : ""} réalisée
                    {unpricedItems.length > 1 ? "s" : ""} pour ce patient en attente d'un tarif configuré (
                    {unpricedItems.map((item) => item.libelle).join(", ")}) — non facturable{unpricedItems.length > 1 ? "s" : ""}{" "}
                    tant qu'aucun tarif actif n'existe pour cet acte.
                  </p>
                )}
              </div>
            )}

            <div>
              <Label>Site</Label>
              <Select
                value={siteId ?? ""}
                onChange={(e) => setSiteId(e.target.value ? Number(e.target.value) : null)}
                disabled={sitesQuery.isLoading}
              >
                <option value="">Sélectionner un site</option>
                {(sitesQuery.data ?? []).map((site) => (
                  <option key={site.id} value={site.id}>
                    {site.name}
                  </option>
                ))}
              </Select>
            </div>

            {error && (
              <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
                {error}
              </p>
            )}
          </div>
        )}

        <DialogFooter>
          {created ? (
            <Button onClick={() => onOpenChange(false)}>Fermer</Button>
          ) : (
            <>
              <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={createInvoice.isPending}>
                Annuler
              </Button>
              <Button onClick={handleSubmit} disabled={!canSubmit || createInvoice.isPending}>
                {createInvoice.isPending && <LoaderCircle size={16} className="animate-spin" />}
                Créer la facture
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
