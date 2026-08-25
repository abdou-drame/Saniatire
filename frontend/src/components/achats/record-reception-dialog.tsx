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
import { useCreateReception } from "@/hooks/use-purchase-orders";
import { apiErrorMessage } from "@/lib/api-error";
import type { PurchaseOrderItem, ReceptionControleQualite } from "@/types/api";

export interface RecordReceptionDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  item: PurchaseOrderItem;
  productLabel: string;
}

function todayIsoDate(): string {
  return new Date().toISOString().slice(0, 10);
}

/**
 * Enregistre une réception sur une ligne de commande. C'est le BACKEND qui,
 * en cas de contrôle qualité "conforme", crée automatiquement le lot produit
 * (ProductBatch) et le mouvement de stock d'entrée correspondants, et
 * recalcule le statut de la commande — cette boîte de dialogue se contente
 * d'appeler la mutation puis de laisser React Query refetch ; elle ne simule
 * jamais ce mouvement ni ce recalcul localement.
 */
export function RecordReceptionDialog({ open, onOpenChange, item, productLabel }: RecordReceptionDialogProps) {
  const [quantite, setQuantite] = useState("");
  const [dateReception, setDateReception] = useState(todayIsoDate());
  const [controleQualite, setControleQualite] = useState<ReceptionControleQualite>("conforme");
  const [numeroLot, setNumeroLot] = useState("");
  const [datePeremption, setDatePeremption] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);

  const createReception = useCreateReception();

  useEffect(() => {
    if (!open) {
      setQuantite("");
      setDateReception(todayIsoDate());
      setControleQualite("conforme");
      setNumeroLot("");
      setDatePeremption("");
      setError(null);
      setSuccess(false);
    }
  }, [open]);

  const quantiteNumber = Number(quantite);
  const canSubmit =
    quantite.trim() !== "" && quantiteNumber > 0 && dateReception.trim() !== "" && numeroLot.trim() !== "";

  function handleSubmit() {
    setError(null);
    createReception.mutate(
      {
        purchaseOrderItemId: item.id,
        quantite_recue: quantiteNumber,
        date_reception: dateReception,
        controle_qualite: controleQualite,
        numero_lot: numeroLot,
        date_peremption: datePeremption,
      },
      {
        onSuccess: () => setSuccess(true),
        onError: (err) => setError(apiErrorMessage(err)),
      },
    );
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Enregistrer une réception</DialogTitle>
          <DialogDescription>{productLabel}</DialogDescription>
        </DialogHeader>

        {success ? (
          <div className="space-y-4 py-4 text-center">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-success/10 text-success">
              <CheckCircle2 size={24} />
            </div>
            <p className="text-sm text-text">Réception enregistrée.</p>
            {controleQualite === "conforme" && (
              <p className="text-xs text-text-muted">
                Le lot produit et le mouvement de stock d'entrée ont été générés automatiquement par le serveur.
              </p>
            )}
          </div>
        ) : (
          <div className="space-y-4">
            <p className="text-xs text-text-muted">
              Commandé : {item.quantite_commandee} · Déjà reçu : {item.quantite_recue}
            </p>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <Label>Quantité reçue</Label>
                <input
                  type="number"
                  min={1}
                  value={quantite}
                  onChange={(e) => setQuantite(e.target.value)}
                  className="h-9 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                />
              </div>
              <div>
                <Label>Date de réception</Label>
                <input
                  type="date"
                  value={dateReception}
                  onChange={(e) => setDateReception(e.target.value)}
                  className="h-9 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                />
              </div>
              <div>
                <Label>Contrôle qualité</Label>
                <Select
                  value={controleQualite}
                  onChange={(e) => setControleQualite(e.target.value as ReceptionControleQualite)}
                >
                  <option value="conforme">Conforme</option>
                  <option value="non_conforme">Non conforme</option>
                </Select>
              </div>
              <div>
                <Label>Numéro de lot</Label>
                <input
                  value={numeroLot}
                  onChange={(e) => setNumeroLot(e.target.value)}
                  className="h-9 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                  placeholder="ex. LOT-2026-001"
                />
              </div>
              <div className="sm:col-span-2">
                <Label>Date de péremption</Label>
                <input
                  type="date"
                  value={datePeremption}
                  onChange={(e) => setDatePeremption(e.target.value)}
                  className="h-9 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
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
          {success ? (
            <Button onClick={() => onOpenChange(false)}>Fermer</Button>
          ) : (
            <>
              <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={createReception.isPending}>
                Annuler
              </Button>
              <Button onClick={handleSubmit} disabled={!canSubmit || createReception.isPending}>
                {createReception.isPending && <LoaderCircle size={16} className="animate-spin" />}
                Enregistrer la réception
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
