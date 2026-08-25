import { CheckCircle2, LoaderCircle, Plus, Trash2 } from "lucide-react";
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
import { useCreatePurchaseOrder } from "@/hooks/use-purchase-orders";
import { useProducts } from "@/hooks/use-products";
import { useSites } from "@/hooks/use-sites";
import { useSuppliers } from "@/hooks/use-suppliers";
import { apiErrorMessage } from "@/lib/api-error";

export interface CreatePurchaseOrderDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

interface DraftLine {
  key: number;
  productId: number | null;
  quantite: string;
  prixUnitaire: string;
}

let nextLineKey = 1;
function emptyLine(): DraftLine {
  return { key: nextLineKey++, productId: null, quantite: "", prixUnitaire: "" };
}

/**
 * "Nouvelle commande d'achat" — construit librement une liste de lignes
 * (produit / quantité / prix unitaire) et soumet le tout au backend, seul
 * décideur de la validité de la commande. Aucune règle métier n'est
 * recalculée ici ; toute erreur 422 est affichée verbatim.
 */
export function CreatePurchaseOrderDialog({ open, onOpenChange }: CreatePurchaseOrderDialogProps) {
  const [siteId, setSiteId] = useState<number | null>(null);
  const [supplierId, setSupplierId] = useState<number | null>(null);
  const [lines, setLines] = useState<DraftLine[]>([emptyLine()]);
  const [error, setError] = useState<string | null>(null);
  const [createdId, setCreatedId] = useState<number | null>(null);

  const sitesQuery = useSites();
  const suppliersQuery = useSuppliers();
  const productsQuery = useProducts();
  const createOrder = useCreatePurchaseOrder();

  useEffect(() => {
    if (!open) {
      setSiteId(null);
      setSupplierId(null);
      setLines([emptyLine()]);
      setError(null);
      setCreatedId(null);
    }
  }, [open]);

  function updateLine(key: number, patch: Partial<DraftLine>) {
    setLines((prev) => prev.map((line) => (line.key === key ? { ...line, ...patch } : line)));
  }

  function addLine() {
    setLines((prev) => [...prev, emptyLine()]);
  }

  function removeLine(key: number) {
    setLines((prev) => (prev.length > 1 ? prev.filter((line) => line.key !== key) : prev));
  }

  const validLines = lines.filter(
    (line) => line.productId && line.quantite.trim() !== "" && Number(line.quantite) > 0 && line.prixUnitaire.trim() !== "",
  );
  const canSubmit = Boolean(siteId) && Boolean(supplierId) && validLines.length > 0;

  function handleSubmit() {
    if (!siteId || !supplierId || validLines.length === 0) return;
    setError(null);
    createOrder.mutate(
      {
        site_id: siteId,
        supplier_id: supplierId,
        items: validLines.map((line) => ({
          product_id: line.productId as number,
          quantite_commandee: Number(line.quantite),
          prix_unitaire: Number(line.prixUnitaire),
        })),
      },
      {
        onSuccess: (order) => setCreatedId(order.id),
        onError: (err) => setError(apiErrorMessage(err)),
      },
    );
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>Nouvelle commande d'achat</DialogTitle>
          <DialogDescription>Composez la commande avec un ou plusieurs articles.</DialogDescription>
        </DialogHeader>

        {createdId ? (
          <div className="space-y-4 py-4 text-center">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-success/10 text-success">
              <CheckCircle2 size={24} />
            </div>
            <p className="text-sm text-text">Commande créée en brouillon — #{createdId}</p>
          </div>
        ) : (
          <div className="space-y-4">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
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
              <div>
                <Label>Fournisseur</Label>
                <Select
                  value={supplierId ?? ""}
                  onChange={(e) => setSupplierId(e.target.value ? Number(e.target.value) : null)}
                  disabled={suppliersQuery.isLoading}
                >
                  <option value="">Sélectionner un fournisseur</option>
                  {(suppliersQuery.data?.data ?? []).map((supplier) => (
                    <option key={supplier.id} value={supplier.id}>
                      {supplier.nom}
                    </option>
                  ))}
                </Select>
              </div>
            </div>

            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <Label className="mb-0">Articles</Label>
                <Button type="button" variant="secondary" size="sm" onClick={addLine}>
                  <Plus size={14} />
                  Ajouter une ligne
                </Button>
              </div>

              <div className="space-y-2">
                {lines.map((line) => (
                  <div key={line.key} className="grid grid-cols-12 items-end gap-2 rounded-md border border-border p-2">
                    <div className="col-span-6">
                      <Label className="text-[11px]">Produit</Label>
                      <Select
                        value={line.productId ?? ""}
                        onChange={(e) => updateLine(line.key, { productId: e.target.value ? Number(e.target.value) : null })}
                        disabled={productsQuery.isLoading}
                      >
                        <option value="">Sélectionner</option>
                        {(productsQuery.data?.data ?? []).map((product) => (
                          <option key={product.id} value={product.id}>
                            {product.nom_commercial}
                          </option>
                        ))}
                      </Select>
                    </div>
                    <div className="col-span-2">
                      <Label className="text-[11px]">Quantité</Label>
                      <input
                        type="number"
                        min={1}
                        value={line.quantite}
                        onChange={(e) => updateLine(line.key, { quantite: e.target.value })}
                        className="h-9 w-full rounded-md border border-border bg-bg px-2 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                      />
                    </div>
                    <div className="col-span-3">
                      <Label className="text-[11px]">Prix unitaire</Label>
                      <input
                        type="number"
                        min={0}
                        step="0.01"
                        value={line.prixUnitaire}
                        onChange={(e) => updateLine(line.key, { prixUnitaire: e.target.value })}
                        className="h-9 w-full rounded-md border border-border bg-bg px-2 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                      />
                    </div>
                    <div className="col-span-1 flex justify-end">
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={() => removeLine(line.key)}
                        disabled={lines.length === 1}
                        aria-label="Retirer la ligne"
                      >
                        <Trash2 size={14} />
                      </Button>
                    </div>
                  </div>
                ))}
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
          {createdId ? (
            <Button onClick={() => onOpenChange(false)}>Fermer</Button>
          ) : (
            <>
              <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={createOrder.isPending}>
                Annuler
              </Button>
              <Button onClick={handleSubmit} disabled={!canSubmit || createOrder.isPending}>
                {createOrder.isPending && <LoaderCircle size={16} className="animate-spin" />}
                Créer la commande
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
