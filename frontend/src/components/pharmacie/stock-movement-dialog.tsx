import { CheckCircle2, LoaderCircle } from "lucide-react";
import { useEffect, useState } from "react";
import { Badge } from "@/components/ui/badge";
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
import { useCreateStockMovement, useProductBatches, useProducts } from "@/hooks/use-products";
import { useSites } from "@/hooks/use-sites";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import { MOVEMENT_TYPE_LABEL } from "@/pages/pharmacie/pharmacie-status";
import type { StockMovementType } from "@/types/api";

export interface StockMovementDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  productId?: number;
  siteId?: number;
  onCreated?: () => void;
}

const MOVEMENT_TYPES: StockMovementType[] = ["entree", "sortie", "ajustement", "transfert"];

/**
 * "Nouveau mouvement de stock" dialog. The backend is the sole authority on whether a
 * movement is valid (lot périmé/épuisé, quantité insuffisante, stock négatif, etc.) — this
 * form never pre-filters or pre-validates those rules. It only ever surfaces the backend's
 * verbatim 422 message via apiErrorMessage. The lot picker intentionally lists every batch
 * returned for the product/site, expired or empty included, with a cosmetic badge so the
 * user understands why the backend might refuse a given selection.
 */
export function StockMovementDialog({
  open,
  onOpenChange,
  productId: initialProductId,
  siteId: initialSiteId,
  onCreated,
}: StockMovementDialogProps) {
  const [type, setType] = useState<StockMovementType>("entree");
  const [siteId, setSiteId] = useState("");
  const [productId, setProductId] = useState("");
  const [productBatchId, setProductBatchId] = useState("");
  const [destinationSiteId, setDestinationSiteId] = useState("");
  const [quantite, setQuantite] = useState("");
  const [motif, setMotif] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [createdMovementId, setCreatedMovementId] = useState<number | null>(null);

  const sitesQuery = useSites();
  const productsQuery = useProducts();
  const batchesQuery = useProductBatches({
    productId: productId ? Number(productId) : undefined,
    siteId: siteId ? Number(siteId) : undefined,
  });
  const createMovement = useCreateStockMovement();

  useEffect(() => {
    if (open) {
      setType("entree");
      setSiteId(initialSiteId ? String(initialSiteId) : "");
      setProductId(initialProductId ? String(initialProductId) : "");
      setProductBatchId("");
      setDestinationSiteId("");
      setQuantite("");
      setMotif("");
      setError(null);
      setCreatedMovementId(null);
    }
  }, [open, initialProductId, initialSiteId]);

  useEffect(() => {
    setProductBatchId("");
  }, [productId, siteId]);

  const batches = batchesQuery.data?.data ?? [];
  const today = new Date();

  function handleSubmit() {
    if (!productBatchId || !siteId || !quantite) return;
    if (type === "transfert" && !destinationSiteId) return;
    setError(null);
    createMovement.mutate(
      {
        product_batch_id: Number(productBatchId),
        site_id: Number(siteId),
        destination_site_id: type === "transfert" ? Number(destinationSiteId) : undefined,
        type,
        quantite: Number(quantite),
        motif: motif.trim() || undefined,
      },
      {
        onSuccess: (movement) => {
          setCreatedMovementId(movement.id);
          onCreated?.();
        },
        onError: (err) => setError(apiErrorMessage(err)),
      },
    );
  }

  const canSubmit =
    Boolean(siteId) &&
    Boolean(productId) &&
    Boolean(productBatchId) &&
    Boolean(quantite) &&
    (type !== "transfert" || (Boolean(destinationSiteId) && destinationSiteId !== siteId));

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Nouveau mouvement de stock</DialogTitle>
          <DialogDescription>
            Le serveur valide seul la disponibilité et l'état du lot sélectionné — tout refus est affiché
            tel quel ci-dessous.
          </DialogDescription>
        </DialogHeader>

        {createdMovementId ? (
          <div className="space-y-4 py-4 text-center">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-success/10 text-success">
              <CheckCircle2 size={24} />
            </div>
            <p className="text-sm text-text">Mouvement enregistré — #{createdMovementId}</p>
          </div>
        ) : (
          <div className="space-y-4">
            <div>
              <Label>Type de mouvement</Label>
              <Select value={type} onChange={(e) => setType(e.target.value as StockMovementType)}>
                {MOVEMENT_TYPES.map((t) => (
                  <option key={t} value={t}>
                    {MOVEMENT_TYPE_LABEL[t]}
                  </option>
                ))}
              </Select>
            </div>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <Label>Site source</Label>
                <Select value={siteId} onChange={(e) => setSiteId(e.target.value)}>
                  <option value="">Sélectionner un site</option>
                  {(sitesQuery.data ?? []).map((site) => (
                    <option key={site.id} value={site.id}>
                      {site.name}
                    </option>
                  ))}
                </Select>
              </div>
              <div>
                <Label>Produit</Label>
                <Select value={productId} onChange={(e) => setProductId(e.target.value)}>
                  <option value="">Sélectionner un produit</option>
                  {(productsQuery.data?.data ?? []).map((product) => (
                    <option key={product.id} value={product.id}>
                      {product.nom_commercial}
                    </option>
                  ))}
                </Select>
              </div>
            </div>

            {type === "transfert" && (
              <div>
                <Label>Site destination</Label>
                <Select value={destinationSiteId} onChange={(e) => setDestinationSiteId(e.target.value)}>
                  <option value="">Sélectionner un site destination</option>
                  {(sitesQuery.data ?? [])
                    .filter((site) => String(site.id) !== siteId)
                    .map((site) => (
                      <option key={site.id} value={site.id}>
                        {site.name}
                      </option>
                    ))}
                </Select>
              </div>
            )}

            <div>
              <Label>Lot</Label>
              {!productId || !siteId ? (
                <p className="text-xs text-text-subtle">
                  Sélectionnez un produit et un site pour afficher les lots disponibles.
                </p>
              ) : batchesQuery.isLoading ? (
                <p className="text-xs text-text-subtle">Chargement des lots...</p>
              ) : batchesQuery.isError ? (
                <p className="text-xs text-danger">{apiErrorMessage(batchesQuery.error)}</p>
              ) : batches.length === 0 ? (
                <p className="text-xs text-text-subtle">Aucun lot pour ce produit sur ce site.</p>
              ) : (
                <div className="max-h-48 space-y-1.5 overflow-y-auto rounded-md border border-border p-2">
                  {batches.map((batch) => {
                    const isExpired = new Date(batch.date_peremption) < today;
                    const isEmpty = batch.quantite_stock <= 0;
                    const isSelected = productBatchId === String(batch.id);
                    return (
                      <button
                        type="button"
                        key={batch.id}
                        onClick={() => setProductBatchId(String(batch.id))}
                        className={`flex w-full items-center justify-between gap-3 rounded-md border px-3 py-2 text-left text-sm ${
                          isSelected ? "border-accent bg-accent/5" : "border-border hover:bg-surface-hover"
                        }`}
                      >
                        <span className="text-text">
                          Lot {batch.numero_lot} — péremption {formatDate(batch.date_peremption)} — qté{" "}
                          {batch.quantite_stock}
                        </span>
                        <span className="flex shrink-0 gap-1.5">
                          {isExpired && <Badge status="danger">Périmé</Badge>}
                          {isEmpty && <Badge status="warning">Épuisé</Badge>}
                        </span>
                      </button>
                    );
                  })}
                </div>
              )}
            </div>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <Label>Quantité</Label>
                <Input
                  type="number"
                  min={1}
                  value={quantite}
                  onChange={(e) => setQuantite(e.target.value)}
                />
              </div>
              <div>
                <Label>Motif</Label>
                <Input value={motif} onChange={(e) => setMotif(e.target.value)} placeholder="Optionnel" />
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
          {createdMovementId ? (
            <Button onClick={() => onOpenChange(false)}>Fermer</Button>
          ) : (
            <>
              <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={createMovement.isPending}>
                Annuler
              </Button>
              <Button onClick={handleSubmit} disabled={!canSubmit || createMovement.isPending}>
                {createMovement.isPending && <LoaderCircle size={16} className="animate-spin" />}
                Enregistrer
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
