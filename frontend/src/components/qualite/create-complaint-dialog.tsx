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
import { Textarea } from "@/components/ui/textarea";
import { PatientPicker } from "@/components/clinical/patient-picker";
import { useCreateComplaint } from "@/hooks/use-complaints";
import { apiErrorMessage } from "@/lib/api-error";
import type { Patient } from "@/types/api";

export interface CreateComplaintDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/**
 * Dialogue de création uniquement. `statut` et `gestionnaire_id` ne sont
 * jamais envoyés : le backend force statut = 'ouverte' et gestionnaire_id
 * n'est jamais définissable à la création (seul /assign le permet).
 * Le picker patient utilise usePatientSearch() (recherche libre par nom, via
 * PatientPicker) plutôt que usePatientsDirectory() — une réclamation doit
 * pouvoir cibler n'importe quel patient de la structure, pas seulement ceux
 * de la première page du répertoire.
 */
export function CreateComplaintDialog({ open, onOpenChange }: CreateComplaintDialogProps) {
  const createComplaint = useCreateComplaint();

  const [patient, setPatient] = useState<Patient | null>(null);
  const [motif, setMotif] = useState("");
  const [description, setDescription] = useState("");
  const [serviceConcerne, setServiceConcerne] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) return;
    setPatient(null);
    setMotif("");
    setDescription("");
    setServiceConcerne("");
    setFieldErrors({});
    setSubmitError(null);
  }, [open]);

  function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!patient) errors.patient = "Sélectionnez un patient.";
    if (!motif.trim()) errors.motif = "Le motif est obligatoire.";
    if (!description.trim()) errors.description = "La description est obligatoire.";
    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitError(null);
    if (!validate() || !patient) return;

    try {
      await createComplaint.mutateAsync({
        patient_id: patient.id,
        motif: motif.trim(),
        description: description.trim(),
        service_concerne: serviceConcerne.trim() || undefined,
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
          <DialogTitle>Nouvelle réclamation</DialogTitle>
          <DialogDescription>
            La réclamation sera créée avec le statut « Ouverte », non assignée. L'assignation se fait ensuite
            depuis le détail de la réclamation.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          {submitError && (
            <p className="rounded-md border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">
              {submitError}
            </p>
          )}

          <div>
            <Label htmlFor="complaint-patient">Patient</Label>
            <PatientPicker value={patient} onChange={setPatient} />
            <FieldError>{fieldErrors.patient}</FieldError>
          </div>

          <div>
            <Label htmlFor="complaint-motif">Motif</Label>
            <Input id="complaint-motif" value={motif} onChange={(e) => setMotif(e.target.value)} />
            <FieldError>{fieldErrors.motif}</FieldError>
          </div>

          <div>
            <Label htmlFor="complaint-description">Description</Label>
            <Textarea
              id="complaint-description"
              rows={4}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
            />
            <FieldError>{fieldErrors.description}</FieldError>
          </div>

          <div>
            <Label htmlFor="complaint-service">Service concerné (optionnel)</Label>
            <Input
              id="complaint-service"
              value={serviceConcerne}
              onChange={(e) => setServiceConcerne(e.target.value)}
            />
          </div>

          <DialogFooter>
            <Button type="button" variant="secondary" onClick={() => onOpenChange(false)}>
              Annuler
            </Button>
            <Button type="submit" disabled={createComplaint.isPending}>
              {createComplaint.isPending ? "Envoi..." : "Créer la réclamation"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
