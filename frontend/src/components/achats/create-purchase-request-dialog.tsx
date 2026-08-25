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
import { useCreatePurchaseRequest } from "@/hooks/use-purchase-orders";
import { useProducts } from "@/hooks/use-products";
import { useSites } from "@/hooks/use-sites";
import { apiErrorMessage } from "@/lib/api-error";

export interface CreatePurchaseRequestDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/**
 * "Nouvelle demande d'achat" — envoie la demande telle quelle au backend, seul
 * décideur de sa validité (produit/site/quantité). Toute erreur 422 est
 * affichée verbatim via apiErrorMessage, jamais reformulée côté client.
 */
export function CreatePurchaseRequestDialog({ open, onOpenChange }: CreatePurchaseRequestDialogProps) {
  const [siteId, setSiteId] = useState<number | null>(null);
  const [productId, setProductId] = useState<number | null>(null);
  const [quantite, setQuantite] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [createdId, setCreatedId] = useState<number | null>(null);

  const sitesQuery = useSites();
  const productsQuery = useProducts();
  const createRequest = useCreatePurchaseRequest();

  useEffect(() => {
    if (!open) {
      setSiteId(null);
      setProductId(null);
      setQuantite("");
      setError(null);
      setCreatedId(null);
    }
  }, [open]);

  const quantiteNumber = Number(quantite);
  const canSubmit = Boolean(siteId) && Boolean(productId) && quantite.trim() !== "" && quantiteNumber > 0;

  function handleSubmit() {
    if (!siteId || !productId) return;
    setError(null);
    createRequest.mutate(
      { site_id: siteId, product_id: productId, quantite: quantiteNumber },
      {
        onSuccess: (request) => setCreatedId(request.id),
        onError: (err) => setError(apiErrorMessage(err)),
      },
    );
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Nouvelle demande d'achat</DialogTitle>
          <DialogDescription>Demandez l'approvisionnement d'un produit pour un site.</DialogDescription>
        </DialogHeader>

        {createdId ? (
          <div className="space-y-4 py-4 text-center">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-success/10 text-success">
              <CheckCircle2 size={24} />
            </div>
            <p className="text-sm text-text">Demande créée — #{createdId}</p>
          </div>
        ) : (
          <div className="space-y-4">
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
              <Label>Produit</Label>
              <Select
                value={productId ?? ""}
                onChange={(e) => setProductId(e.target.value ? Number(e.target.value) : null)}
                disabled={productsQuery.isLoading}
              >
                <option value="">Sélectionner un produit</option>
                {(productsQuery.data?.data ?? []).map((product) => (
                  <option key={product.id} value={product.id}>
                    {product.nom_commercial}
                  </option>
                ))}
              </Select>
            </div>

            <div>
              <Label>Quantité</Label>
              <input
                type="number"
                min={1}
                value={quantite}
                onChange={(e) => setQuantite(e.target.value)}
                className="h-9 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                placeholder="ex. 10"
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
          {createdId ? (
            <Button onClick={() => onOpenChange(false)}>Fermer</Button>
          ) : (
            <>
              <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={createRequest.isPending}>
                Annuler
              </Button>
              <Button onClick={handleSubmit} disabled={!canSubmit || createRequest.isPending}>
                {createRequest.isPending && <LoaderCircle size={16} className="animate-spin" />}
                Créer la demande
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
