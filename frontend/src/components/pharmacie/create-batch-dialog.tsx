import { LoaderCircle } from "lucide-react";
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
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { useCreateProductBatch } from "@/hooks/use-products";
import { useSites } from "@/hooks/use-sites";
import { useSuppliers } from "@/hooks/use-suppliers";
import { apiErrorMessage } from "@/lib/api-error";

export interface CreateBatchDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  productId: number;
  onCreated?: () => void;
}

/**
 * "Nouveau lot" dialog, lancé depuis la fiche d'un produit du catalogue.
 * Soumet exactement ce qui est saisi ici — le backend est seul juge de la
 * validité (ex. date de péremption passée) ; tout refus 422/403 est affiché
 * tel quel via apiErrorMessage.
 */
export function CreateBatchDialog({ open, onOpenChange, productId, onCreated }: CreateBatchDialogProps) {
  const [siteId, setSiteId] = useState("");
  const [supplierId, setSupplierId] = useState("");
  const [numeroLot, setNumeroLot] = useState("");
  const [datePeremption, setDatePeremption] = useState("");
  const [quantiteStock, setQuantiteStock] = useState("");
  const [prixAchatUnitaire, setPrixAchatUnitaire] = useState("");
  const [error, setError] = useState<string | null>(null);

  const sitesQuery = useSites();
  const suppliersQuery = useSuppliers();
  const createBatch = useCreateProductBatch();

  useEffect(() => {
    if (!open) {
      setSiteId("");
      setSupplierId("");
      setNumeroLot("");
      setDatePeremption("");
      setQuantiteStock("");
      setPrixAchatUnitaire("");
      setError(null);
    }
  }, [open]);

  const canSubmit =
    Boolean(siteId) &&
    numeroLot.trim().length > 0 &&
    Boolean(datePeremption) &&
    quantiteStock.trim().length > 0 &&
    prixAchatUnitaire.trim().length > 0;

  function handleSubmit() {
    if (!canSubmit) return;
    setError(null);
    createBatch.mutate(
      {
        product_id: productId,
        site_id: Number(siteId),
        supplier_id: supplierId ? Number(supplierId) : undefined,
        numero_lot: numeroLot.trim(),
        date_peremption: datePeremption,
        quantite_stock: Number(quantiteStock),
        prix_achat_unitaire: Number(prixAchatUnitaire),
      },
      {
        onSuccess: () => {
          onCreated?.();
          onOpenChange(false);
        },
        onError: (err) => setError(apiErrorMessage(err)),
      },
    );
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Nouveau lot</DialogTitle>
          <DialogDescription>Enregistrer un nouveau lot pour ce produit.</DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Site</Label>
              <Select value={siteId} onChange={(e) => setSiteId(e.target.value)} disabled={sitesQuery.isLoading}>
                <option value="">Sélectionner un site</option>
                {(sitesQuery.data ?? []).map((site) => (
                  <option key={site.id} value={site.id}>
                    {site.name}
                  </option>
                ))}
              </Select>
            </div>
            <div>
              <Label>Fournisseur d'origine (optionnel)</Label>
              <Select
                value={supplierId}
                onChange={(e) => setSupplierId(e.target.value)}
                disabled={suppliersQuery.isLoading}
              >
                <option value="">Aucun</option>
                {(suppliersQuery.data?.data ?? []).map((supplier) => (
                  <option key={supplier.id} value={supplier.id}>
                    {supplier.nom}
                  </option>
                ))}
              </Select>
            </div>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Numéro de lot</Label>
              <Input value={numeroLot} onChange={(e) => setNumeroLot(e.target.value)} placeholder="ex. LOT-2026-01" />
            </div>
            <div>
              <Label>Date de péremption</Label>
              <Input type="date" value={datePeremption} onChange={(e) => setDatePeremption(e.target.value)} />
            </div>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Quantité initiale</Label>
              <Input
                type="number"
                min={0}
                value={quantiteStock}
                onChange={(e) => setQuantiteStock(e.target.value)}
              />
            </div>
            <div>
              <Label>Prix d'achat unitaire</Label>
              <Input
                type="number"
                min={0}
                step="0.01"
                value={prixAchatUnitaire}
                onChange={(e) => setPrixAchatUnitaire(e.target.value)}
              />
            </div>
          </div>

          {error && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{error}</p>
          )}
        </div>

        <DialogFooter>
          <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={createBatch.isPending}>
            Annuler
          </Button>
          <Button onClick={handleSubmit} disabled={!canSubmit || createBatch.isPending}>
            {createBatch.isPending && <LoaderCircle size={16} className="animate-spin" />}
            Créer
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
