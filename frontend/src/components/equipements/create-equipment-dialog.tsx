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
import { useCreateEquipment } from "@/hooks/use-biomedical-equipment";
import { useSites } from "@/hooks/use-sites";
import { useSuppliers } from "@/hooks/use-suppliers";
import { apiErrorMessage } from "@/lib/api-error";

export interface CreateEquipmentDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onCreated?: () => void;
}

/**
 * "Nouvel équipement" dialog. Submits exactly what is entered here — the backend
 * is the sole authority on validity; any 422/403 is surfaced verbatim via apiErrorMessage.
 */
export function CreateEquipmentDialog({ open, onOpenChange, onCreated }: CreateEquipmentDialogProps) {
  const [siteId, setSiteId] = useState("");
  const [supplierId, setSupplierId] = useState("");
  const [nom, setNom] = useState("");
  const [categorie, setCategorie] = useState("");
  const [numeroSerie, setNumeroSerie] = useState("");
  const [dateAcquisition, setDateAcquisition] = useState("");
  const [dateFinGarantie, setDateFinGarantie] = useState("");
  const [error, setError] = useState<string | null>(null);

  const sitesQuery = useSites();
  const suppliersQuery = useSuppliers();
  const createEquipment = useCreateEquipment();

  useEffect(() => {
    if (!open) {
      setSiteId("");
      setSupplierId("");
      setNom("");
      setCategorie("");
      setNumeroSerie("");
      setDateAcquisition("");
      setDateFinGarantie("");
      setError(null);
    }
  }, [open]);

  const canSubmit =
    Boolean(siteId) && nom.trim().length > 0 && categorie.trim().length > 0 && numeroSerie.trim().length > 0 &&
    Boolean(dateAcquisition);

  function handleSubmit() {
    if (!canSubmit) return;
    setError(null);
    createEquipment.mutate(
      {
        site_id: Number(siteId),
        supplier_id: supplierId ? Number(supplierId) : undefined,
        nom: nom.trim(),
        categorie: categorie.trim(),
        numero_serie: numeroSerie.trim(),
        date_acquisition: dateAcquisition,
        date_fin_garantie: dateFinGarantie || undefined,
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
          <DialogTitle>Nouvel équipement</DialogTitle>
          <DialogDescription>Enregistrer un nouvel équipement biomédical.</DialogDescription>
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
              <Label>Fournisseur (optionnel)</Label>
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

          <div>
            <Label>Nom de l'équipement</Label>
            <Input value={nom} onChange={(e) => setNom(e.target.value)} placeholder="ex. Échographe" />
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Catégorie</Label>
              <Input
                value={categorie}
                onChange={(e) => setCategorie(e.target.value)}
                placeholder="ex. Imagerie"
              />
            </div>
            <div>
              <Label>Numéro de série</Label>
              <Input value={numeroSerie} onChange={(e) => setNumeroSerie(e.target.value)} placeholder="ex. SN-12345" />
            </div>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Date d'acquisition</Label>
              <Input
                type="date"
                value={dateAcquisition}
                onChange={(e) => setDateAcquisition(e.target.value)}
              />
            </div>
            <div>
              <Label>Fin de garantie (optionnel)</Label>
              <Input
                type="date"
                value={dateFinGarantie}
                onChange={(e) => setDateFinGarantie(e.target.value)}
              />
            </div>
          </div>

          {error && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{error}</p>
          )}
        </div>

        <DialogFooter>
          <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={createEquipment.isPending}>
            Annuler
          </Button>
          <Button onClick={handleSubmit} disabled={!canSubmit || createEquipment.isPending}>
            {createEquipment.isPending && <LoaderCircle size={16} className="animate-spin" />}
            Créer l'équipement
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
