import { useEffect, useState, type FormEvent } from "react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { FieldError, Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import { useCreateSite, useUpdateSite } from "@/hooks/use-sites";
import { apiErrorMessage } from "@/lib/api-error";
import type { Site } from "@/types/api";

export interface SiteFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  site?: Site | null;
}

export function SiteFormDialog({ open, onOpenChange, site = null }: SiteFormDialogProps) {
  const isEditing = Boolean(site);
  const createSite = useCreateSite();
  const updateSite = useUpdateSite();

  const [name, setName] = useState("");
  const [address, setAddress] = useState("");
  const [city, setCity] = useState("");
  const [phone, setPhone] = useState("");
  const [email, setEmail] = useState("");
  const [isActive, setIsActive] = useState(true);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) return;
    setSubmitError(null);
    setFieldErrors({});
    setName(site?.name ?? "");
    setAddress(site?.address ?? "");
    setCity(site?.city ?? "");
    setPhone(site?.phone ?? "");
    setEmail(site?.email ?? "");
    setIsActive(site?.is_active ?? true);
  }, [open, site]);

  function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!name.trim()) errors.name = "Le nom est obligatoire.";
    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitError(null);
    if (!validate()) return;

    const input = {
      name: name.trim(),
      address: address.trim() || null,
      city: city.trim() || null,
      phone: phone.trim() || null,
      email: email.trim() || null,
      is_active: isActive,
    };

    try {
      if (isEditing && site) {
        await updateSite.mutateAsync({ id: site.id, ...input });
      } else {
        await createSite.mutateAsync(input);
      }
      onOpenChange(false);
    } catch (error) {
      setSubmitError(apiErrorMessage(error));
    }
  }

  const isPending = createSite.isPending || updateSite.isPending;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{isEditing ? "Modifier le site" : "Nouveau site"}</DialogTitle>
          <DialogDescription>
            {isEditing ? "Les modifications sont persistées immédiatement côté serveur." : "Le site sera rattaché à votre structure."}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          {submitError && (
            <p className="rounded-md border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">{submitError}</p>
          )}

          <div>
            <Label htmlFor="site-name">Nom</Label>
            <Input id="site-name" value={name} onChange={(e) => setName(e.target.value)} />
            <FieldError>{fieldErrors.name}</FieldError>
          </div>

          <div>
            <Label htmlFor="site-address">Adresse</Label>
            <Input id="site-address" value={address} onChange={(e) => setAddress(e.target.value)} />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label htmlFor="site-city">Ville</Label>
              <Input id="site-city" value={city} onChange={(e) => setCity(e.target.value)} />
            </div>
            <div>
              <Label htmlFor="site-phone">Téléphone</Label>
              <Input id="site-phone" value={phone} onChange={(e) => setPhone(e.target.value)} />
            </div>
          </div>

          <div>
            <Label htmlFor="site-email">Email</Label>
            <Input id="site-email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
          </div>

          <div className="flex items-center gap-2">
            <Switch checked={isActive} onCheckedChange={setIsActive} label="Site actif" />
            <span className="text-sm text-text">Site actif</span>
          </div>

          <DialogFooter>
            <Button type="button" variant="secondary" onClick={() => onOpenChange(false)}>
              Annuler
            </Button>
            <Button type="submit" disabled={isPending}>
              {isPending ? "Enregistrement..." : isEditing ? "Enregistrer" : "Créer le site"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
