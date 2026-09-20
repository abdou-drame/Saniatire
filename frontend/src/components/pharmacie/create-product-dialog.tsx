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
import { useCreateProduct } from "@/hooks/use-products";
import { apiErrorMessage } from "@/lib/api-error";
import { CATEGORIE_LABEL } from "@/pages/pharmacie/pharmacie-status";
import type { ProductCategorie } from "@/types/api";

export interface CreateProductDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onCreated?: (productId: number) => void;
}

const CATEGORIE_OPTIONS: ProductCategorie[] = ["medicament", "consommable", "dispositif_medical"];

/**
 * "Nouveau produit" dialog. Submits exactly what is entered here — the backend
 * is the sole authority on validity; any 422/403 is surfaced verbatim via apiErrorMessage.
 */
export function CreateProductDialog({ open, onOpenChange, onCreated }: CreateProductDialogProps) {
  const [nomCommercial, setNomCommercial] = useState("");
  const [dci, setDci] = useState("");
  const [formeGalenique, setFormeGalenique] = useState("");
  const [dosage, setDosage] = useState("");
  const [categorie, setCategorie] = useState<ProductCategorie>("medicament");
  const [uniteVente, setUniteVente] = useState("");
  const [error, setError] = useState<string | null>(null);

  const createProduct = useCreateProduct();

  useEffect(() => {
    if (!open) {
      setNomCommercial("");
      setDci("");
      setFormeGalenique("");
      setDosage("");
      setCategorie("medicament");
      setUniteVente("");
      setError(null);
    }
  }, [open]);

  const canSubmit =
    nomCommercial.trim().length > 0 &&
    dci.trim().length > 0 &&
    formeGalenique.trim().length > 0 &&
    uniteVente.trim().length > 0;

  function handleSubmit() {
    if (!canSubmit) return;
    setError(null);
    createProduct.mutate(
      {
        nom_commercial: nomCommercial.trim(),
        dci: dci.trim(),
        forme_galenique: formeGalenique.trim(),
        dosage: dosage.trim() || undefined,
        categorie,
        unite_vente: uniteVente.trim(),
      },
      {
        onSuccess: (product) => {
          onCreated?.(product.id);
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
          <DialogTitle>Nouveau produit</DialogTitle>
          <DialogDescription>Enregistrer un nouveau produit au catalogue pharmacie.</DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div>
            <Label>Nom commercial</Label>
            <Input
              value={nomCommercial}
              onChange={(e) => setNomCommercial(e.target.value)}
              placeholder="ex. Doliprane 500mg"
            />
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>DCI</Label>
              <Input value={dci} onChange={(e) => setDci(e.target.value)} placeholder="ex. Paracétamol" />
            </div>
            <div>
              <Label>Dosage (optionnel)</Label>
              <Input value={dosage} onChange={(e) => setDosage(e.target.value)} placeholder="ex. 500mg" />
            </div>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Forme galénique</Label>
              <Input
                value={formeGalenique}
                onChange={(e) => setFormeGalenique(e.target.value)}
                placeholder="ex. Comprimé"
              />
            </div>
            <div>
              <Label>Unité de vente</Label>
              <Input value={uniteVente} onChange={(e) => setUniteVente(e.target.value)} placeholder="ex. Boîte" />
            </div>
          </div>

          <div>
            <Label>Catégorie</Label>
            <Select value={categorie} onChange={(e) => setCategorie(e.target.value as ProductCategorie)}>
              {CATEGORIE_OPTIONS.map((option) => (
                <option key={option} value={option}>
                  {CATEGORIE_LABEL[option]}
                </option>
              ))}
            </Select>
          </div>

          {error && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{error}</p>
          )}
        </div>

        <DialogFooter>
          <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={createProduct.isPending}>
            Annuler
          </Button>
          <Button onClick={handleSubmit} disabled={!canSubmit || createProduct.isPending}>
            {createProduct.isPending && <LoaderCircle size={16} className="animate-spin" />}
            Créer
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
