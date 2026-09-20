import { useEffect, useState, type FormEvent } from "react";
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
import { Textarea } from "@/components/ui/textarea";
import { useCreateLeaveRequest } from "@/hooks/use-leave-requests";
import { apiErrorMessage } from "@/lib/api-error";
import { LEAVE_TYPE_LABEL } from "@/pages/conges/conges-status";
import type { LeaveRequestType } from "@/types/api";

export interface LeaveRequestFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

const LEAVE_TYPE_VALUES: LeaveRequestType[] = ["conge_annuel", "maladie", "autre"];

/**
 * Dialogue de création uniquement — les demandes de congé ne sont pas
 * modifiables (aucune route PATCH/PUT sur /leave-requests côté backend, seuls
 * /validate et /refuse existent). `user_id` et `statut` ne sont jamais
 * envoyés : le backend les force-set (utilisateur courant, statut "demande").
 */
export function LeaveRequestFormDialog({ open, onOpenChange }: LeaveRequestFormDialogProps) {
  const createLeaveRequest = useCreateLeaveRequest();

  const [type, setType] = useState<LeaveRequestType>("conge_annuel");
  const [dateDebut, setDateDebut] = useState("");
  const [dateFin, setDateFin] = useState("");
  const [commentaire, setCommentaire] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) return;
    setType("conge_annuel");
    setDateDebut("");
    setDateFin("");
    setCommentaire("");
    setFieldErrors({});
    setSubmitError(null);
  }, [open]);

  function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!dateDebut) errors.dateDebut = "La date de début est obligatoire.";
    if (!dateFin) errors.dateFin = "La date de fin est obligatoire.";
    if (dateDebut && dateFin && dateFin < dateDebut) {
      errors.dateFin = "La date de fin doit être postérieure ou égale à la date de début.";
    }
    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitError(null);
    if (!validate()) return;

    try {
      await createLeaveRequest.mutateAsync({
        type,
        date_debut: dateDebut,
        date_fin: dateFin,
        commentaire: commentaire.trim() || undefined,
      });
      onOpenChange(false);
    } catch (error) {
      setSubmitError(apiErrorMessage(error));
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Nouvelle demande de congé</DialogTitle>
          <DialogDescription>
            Votre demande sera soumise à validation. Le statut initial et le demandeur sont définis
            automatiquement par le serveur.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          {submitError && (
            <p className="rounded-md border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">
              {submitError}
            </p>
          )}

          <div>
            <Label htmlFor="leave-type">Type</Label>
            <Select id="leave-type" value={type} onChange={(e) => setType(e.target.value as LeaveRequestType)}>
              {LEAVE_TYPE_VALUES.map((value) => (
                <option key={value} value={value}>
                  {LEAVE_TYPE_LABEL[value]}
                </option>
              ))}
            </Select>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label htmlFor="leave-date-debut">Date de début</Label>
              <Input
                id="leave-date-debut"
                type="date"
                value={dateDebut}
                onChange={(e) => setDateDebut(e.target.value)}
              />
              <FieldError>{fieldErrors.dateDebut}</FieldError>
            </div>
            <div>
              <Label htmlFor="leave-date-fin">Date de fin</Label>
              <Input id="leave-date-fin" type="date" value={dateFin} onChange={(e) => setDateFin(e.target.value)} />
              <FieldError>{fieldErrors.dateFin}</FieldError>
            </div>
          </div>

          <div>
            <Label htmlFor="leave-commentaire">Commentaire</Label>
            <Textarea
              id="leave-commentaire"
              rows={3}
              value={commentaire}
              onChange={(e) => setCommentaire(e.target.value)}
            />
          </div>

          <DialogFooter>
            <Button type="button" variant="secondary" onClick={() => onOpenChange(false)}>
              Annuler
            </Button>
            <Button type="submit" disabled={createLeaveRequest.isPending}>
              {createLeaveRequest.isPending ? "Envoi..." : "Envoyer la demande"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
