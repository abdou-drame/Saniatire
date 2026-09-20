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
import { FieldError, Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { useCreateExternalPrescriber, useUpdateExternalPrescriber } from "@/hooks/use-external-prescribers";
import { apiErrorMessage } from "@/lib/api-error";
import type { PrescriberUser } from "@/types/api";

export interface ExternalPrescriberFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Present when editing an existing prescriber; absent/null when creating one. */
  prescriber?: PrescriberUser | null;
}

/**
 * Création/édition d'une fiche prescripteur externe (destinataire des
 * demandes d'analyses/imagerie hors structure). L'activation du portail
 * (lien envoyé au prescripteur pour définir son propre mot de passe) est
 * une action séparée, déclenchée depuis la page — pas ce formulaire.
 * structure_id n'est jamais un champ ici : forcé côté serveur.
 */
export function ExternalPrescriberFormDialog({ open, onOpenChange, prescriber = null }: ExternalPrescriberFormDialogProps) {
  const isEditing = Boolean(prescriber);
  const createPrescriber = useCreateExternalPrescriber();
  const updatePrescriber = useUpdateExternalPrescriber();

  const [nom, setNom] = useState("");
  const [specialite, setSpecialite] = useState("");
  const [email, setEmail] = useState("");
  const [telephone, setTelephone] = useState("");
  const [statut, setStatut] = useState<"actif" | "inactif">("actif");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) return;
    setSubmitError(null);
    setFieldErrors({});
    if (prescriber) {
      setNom(prescriber.nom);
      setSpecialite(prescriber.specialite ?? "");
      setEmail(prescriber.email);
      setTelephone(prescriber.telephone ?? "");
      setStatut(prescriber.statut);
    } else {
      setNom("");
      setSpecialite("");
      setEmail("");
      setTelephone("");
      setStatut("actif");
    }
  }, [open, prescriber]);

  const isPending = createPrescriber.isPending || updatePrescriber.isPending;

  function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!nom.trim()) errors.nom = "Le nom est obligatoire.";
    if (!email.trim()) errors.email = "L'email est obligatoire.";
    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  }

  function handleSubmit() {
    setSubmitError(null);
    if (!validate()) return;

    const payload = {
      nom: nom.trim(),
      specialite: specialite.trim() || undefined,
      email: email.trim(),
      telephone: telephone.trim() || undefined,
      statut,
    };

    if (isEditing && prescriber) {
      updatePrescriber.mutate(
        { id: prescriber.id, ...payload },
        {
          onSuccess: () => onOpenChange(false),
          onError: (err) => setSubmitError(apiErrorMessage(err)),
        },
      );
      return;
    }

    createPrescriber.mutate(payload, {
      onSuccess: () => onOpenChange(false),
      onError: (err) => setSubmitError(apiErrorMessage(err)),
    });
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{isEditing ? "Modifier le prescripteur" : "Nouveau prescripteur externe"}</DialogTitle>
          <DialogDescription>
            Fiche d'un professionnel de santé externe à la structure, destinataire de demandes d'analyses ou
            d'imagerie.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {submitError && (
            <p className="rounded-md border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">
              {submitError}
            </p>
          )}

          <div>
            <Label>Nom</Label>
            <Input value={nom} onChange={(e) => setNom(e.target.value)} />
            <FieldError>{fieldErrors.nom}</FieldError>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Spécialité</Label>
              <Input value={specialite} onChange={(e) => setSpecialite(e.target.value)} />
              <FieldError>{fieldErrors.specialite}</FieldError>
            </div>
            <div>
              <Label>Statut</Label>
              <Select value={statut} onChange={(e) => setStatut(e.target.value as "actif" | "inactif")}>
                <option value="actif">Actif</option>
                <option value="inactif">Inactif</option>
              </Select>
            </div>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Email</Label>
              <Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
              <FieldError>{fieldErrors.email}</FieldError>
            </div>
            <div>
              <Label>Téléphone</Label>
              <Input value={telephone} onChange={(e) => setTelephone(e.target.value)} />
              <FieldError>{fieldErrors.telephone}</FieldError>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={isPending}>
            Annuler
          </Button>
          <Button onClick={handleSubmit} disabled={isPending}>
            {isPending && <LoaderCircle size={16} className="animate-spin" />}
            {isEditing ? "Enregistrer" : "Créer la fiche"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
