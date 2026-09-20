import { useEffect, useState, type FormEvent } from "react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { FieldError, Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { useSites } from "@/hooks/use-sites";
import { useCreateWard, useUpdateWard } from "@/hooks/use-wards";
import { apiErrorMessage } from "@/lib/api-error";
import type { Ward } from "@/types/api";

export interface WardFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  ward?: Ward | null;
}

export function WardFormDialog({ open, onOpenChange, ward = null }: WardFormDialogProps) {
  const isEditing = Boolean(ward);
  const sitesQuery = useSites();
  const createWard = useCreateWard();
  const updateWard = useUpdateWard();

  const [siteId, setSiteId] = useState<string>("");
  const [name, setName] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) return;
    setSubmitError(null);
    setFieldErrors({});
    setSiteId(ward ? String(ward.site_id) : "");
    setName(ward?.name ?? "");
  }, [open, ward]);

  function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!siteId) errors.site_id = "Sélectionnez un site.";
    if (!name.trim()) errors.name = "Le nom du service est obligatoire.";
    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitError(null);
    if (!validate()) return;

    try {
      if (isEditing && ward) {
        await updateWard.mutateAsync({ id: ward.id, site_id: Number(siteId), name: name.trim() });
      } else {
        await createWard.mutateAsync({ site_id: Number(siteId), name: name.trim() });
      }
      onOpenChange(false);
    } catch (error) {
      setSubmitError(apiErrorMessage(error));
    }
  }

  const isPending = createWard.isPending || updateWard.isPending;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{isEditing ? "Modifier le service" : "Nouveau service"}</DialogTitle>
          <DialogDescription>
            {isEditing ? "Les modifications sont persistées immédiatement côté serveur." : "Le service (ward) sera rattaché au site choisi."}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          {submitError && (
            <p className="rounded-md border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">{submitError}</p>
          )}

          <div>
            <Label htmlFor="ward-site">Site</Label>
            <Select id="ward-site" value={siteId} onChange={(e) => setSiteId(e.target.value)} disabled={sitesQuery.isLoading}>
              <option value="">Sélectionner...</option>
              {(sitesQuery.data ?? []).map((site) => (
                <option key={site.id} value={site.id}>
                  {site.name}
                </option>
              ))}
            </Select>
            <FieldError>{fieldErrors.site_id}</FieldError>
          </div>

          <div>
            <Label htmlFor="ward-name">Nom du service</Label>
            <Input id="ward-name" value={name} onChange={(e) => setName(e.target.value)} placeholder="Ex. Médecine interne" />
            <FieldError>{fieldErrors.name}</FieldError>
          </div>

          <DialogFooter>
            <Button type="button" variant="secondary" onClick={() => onOpenChange(false)}>
              Annuler
            </Button>
            <Button type="submit" disabled={isPending}>
              {isPending ? "Enregistrement..." : isEditing ? "Enregistrer" : "Créer le service"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
