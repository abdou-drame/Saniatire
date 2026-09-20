import { LoaderCircle } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
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
import { useCreateStockThreshold, useStockThresholds, useUpdateStockThreshold } from "@/hooks/use-products";
import { useSites } from "@/hooks/use-sites";
import { apiErrorMessage } from "@/lib/api-error";

export interface StockThresholdDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  productId: number;
  onSaved?: () => void;
}

/**
 * Configure le seuil d'alerte (StockThreshold) pour le couple produit/site déjà
 * sélectionné. S'il existe déjà un seuil pour ce site, le formulaire bascule
 * automatiquement en modification (update) plutôt qu'en création, pour respecter
 * la contrainte unique(product_id, site_id) côté backend sans jamais la
 * dupliquer côté client — le backend reste seul juge en cas d'écart.
 */
export function StockThresholdDialog({ open, onOpenChange, productId, onSaved }: StockThresholdDialogProps) {
  const [siteId, setSiteId] = useState("");
  const [seuilMinimum, setSeuilMinimum] = useState("");
  const [error, setError] = useState<string | null>(null);

  const sitesQuery = useSites();
  const thresholdsQuery = useStockThresholds({ productId });
  const createThreshold = useCreateStockThreshold();
  const updateThreshold = useUpdateStockThreshold();

  useEffect(() => {
    if (!open) {
      setSiteId("");
      setSeuilMinimum("");
      setError(null);
    }
  }, [open]);

  const existingThreshold = useMemo(
    () => (thresholdsQuery.data?.data ?? []).find((t) => String(t.site_id) === siteId),
    [thresholdsQuery.data, siteId],
  );

  useEffect(() => {
    setSeuilMinimum(existingThreshold ? String(existingThreshold.seuil_minimum) : "");
  }, [existingThreshold]);

  const canSubmit = Boolean(siteId) && seuilMinimum.trim().length > 0;
  const isPending = createThreshold.isPending || updateThreshold.isPending;

  function handleSubmit() {
    if (!canSubmit) return;
    setError(null);
    const onSettled = {
      onSuccess: () => {
        onSaved?.();
        onOpenChange(false);
      },
      onError: (err: unknown) => setError(apiErrorMessage(err)),
    };
    if (existingThreshold) {
      updateThreshold.mutate({ id: existingThreshold.id, seuil_minimum: Number(seuilMinimum) }, onSettled);
    } else {
      createThreshold.mutate(
        { product_id: productId, site_id: Number(siteId), seuil_minimum: Number(seuilMinimum) },
        onSettled,
      );
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Configurer un seuil d'alerte</DialogTitle>
          <DialogDescription>
            Niveau de stock minimum, par site, en dessous duquel ce produit apparaît dans les alertes de seuil bas.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
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
            <Label>Seuil minimum</Label>
            <Input
              type="number"
              min={0}
              value={seuilMinimum}
              onChange={(e) => setSeuilMinimum(e.target.value)}
            />
            {existingThreshold && (
              <p className="mt-1 text-xs text-text-subtle">
                Un seuil est déjà configuré pour ce site — l'enregistrement le modifiera.
              </p>
            )}
          </div>

          {error && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{error}</p>
          )}
        </div>

        <DialogFooter>
          <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={isPending}>
            Annuler
          </Button>
          <Button onClick={handleSubmit} disabled={!canSubmit || isPending}>
            {isPending && <LoaderCircle size={16} className="animate-spin" />}
            {existingThreshold ? "Modifier" : "Créer"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
