import { AlertTriangle } from "lucide-react";
import { useState, type FormEvent } from "react";
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
import { useCreatePatient, type CreatePatientResult } from "@/hooks/use-create-patient";
import { apiErrorMessage } from "@/lib/api-error";
import type { Patient } from "@/types/api";

export interface PatientQuickCreateDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Called once the secretary confirms which record to use going forward (new or an existing duplicate). */
  onPatientReady: (patient: Patient) => void;
}

interface FormState {
  first_name: string;
  last_name: string;
  sex: "M" | "F";
  birth_date: string;
  phone: string;
  emergency_contact_name: string;
  emergency_contact_phone: string;
}

const EMPTY_FORM: FormState = {
  first_name: "",
  last_name: "",
  sex: "M",
  birth_date: "",
  phone: "",
  emergency_contact_name: "",
  emergency_contact_phone: "",
};

export function PatientQuickCreateDialog({ open, onOpenChange, onPatientReady }: PatientQuickCreateDialogProps) {
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [result, setResult] = useState<CreatePatientResult | null>(null);
  const createPatient = useCreatePatient();

  function reset() {
    setForm(EMPTY_FORM);
    setFieldErrors({});
    setSubmitError(null);
    setResult(null);
  }

  function handleOpenChange(next: boolean) {
    if (!next) reset();
    onOpenChange(next);
  }

  function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!form.first_name.trim()) errors.first_name = "Le prénom est obligatoire.";
    if (!form.last_name.trim()) errors.last_name = "Le nom est obligatoire.";
    if (!form.birth_date) errors.birth_date = "La date de naissance est obligatoire.";
    else if (new Date(form.birth_date) > new Date()) errors.birth_date = "La date de naissance ne peut pas être future.";
    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitError(null);
    if (!validate()) return;

    try {
      const created = await createPatient.mutateAsync({
        first_name: form.first_name.trim(),
        last_name: form.last_name.trim(),
        sex: form.sex,
        birth_date: form.birth_date,
        phone: form.phone.trim() || undefined,
        emergency_contact_name: form.emergency_contact_name.trim() || undefined,
        emergency_contact_phone: form.emergency_contact_phone.trim() || undefined,
      });
      if (created.possible_duplicates.length === 0) {
        onPatientReady(created.data);
        handleOpenChange(false);
      } else {
        // Hold the dialog open so the secretary can decide: keep the new
        // record just created, or use an existing one instead — the backend
        // never blocks creation, so the new patient already exists either way.
        setResult(created);
      }
    } catch (error) {
      setSubmitError(apiErrorMessage(error));
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Nouveau patient</DialogTitle>
          <DialogDescription>Identité minimale — le dossier complet pourra être enrichi ensuite.</DialogDescription>
        </DialogHeader>

        {result ? (
          <div className="space-y-4">
            <div className="flex items-start gap-3 rounded-md border border-warning/30 bg-warning/5 px-3 py-2.5">
              <AlertTriangle size={18} className="mt-0.5 shrink-0 text-warning" />
              <div className="space-y-1 text-sm">
                <p className="font-medium text-text">Doublon(s) potentiel(s) détecté(s)</p>
                <p className="text-text-muted">
                  Un patient portant le même nom et la même date de naissance existe déjà :{" "}
                  {result.possible_duplicates.map((d) => d.patient_number).join(", ")}. Le nouveau dossier{" "}
                  <span className="font-tabular">{result.data.patient_number}</span> a bien été créé — confirmez
                  qu'il s'agit d'une personne différente, ou reprenez le dossier existant.
                </p>
              </div>
            </div>
            <DialogFooter>
              <Button
                variant="secondary"
                onClick={() => {
                  onPatientReady(result.data);
                  handleOpenChange(false);
                }}
              >
                Confirmer nouveau patient
              </Button>
              <Button
                onClick={() => {
                  // Existing summary only carries id/patient_number; the
                  // caller (PatientPicker) needs a full Patient shape, so we
                  // hand back what we have — good enough to identify the
                  // record for the appointment/queue flow that follows.
                  const dup = result.possible_duplicates[0];
                  onPatientReady({ ...result.data, id: dup.id, patient_number: dup.patient_number });
                  handleOpenChange(false);
                }}
              >
                Reprendre le dossier existant
              </Button>
            </DialogFooter>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-4">
            {submitError && <p className="rounded-md border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">{submitError}</p>}

            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label htmlFor="pqc-first-name">Prénom</Label>
                <Input
                  id="pqc-first-name"
                  value={form.first_name}
                  onChange={(e) => setForm((f) => ({ ...f, first_name: e.target.value }))}
                />
                <FieldError>{fieldErrors.first_name}</FieldError>
              </div>
              <div>
                <Label htmlFor="pqc-last-name">Nom</Label>
                <Input
                  id="pqc-last-name"
                  value={form.last_name}
                  onChange={(e) => setForm((f) => ({ ...f, last_name: e.target.value }))}
                />
                <FieldError>{fieldErrors.last_name}</FieldError>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label htmlFor="pqc-sex">Sexe</Label>
                <Select
                  id="pqc-sex"
                  value={form.sex}
                  onChange={(e) => setForm((f) => ({ ...f, sex: e.target.value as "M" | "F" }))}
                >
                  <option value="M">Masculin</option>
                  <option value="F">Féminin</option>
                </Select>
              </div>
              <div>
                <Label htmlFor="pqc-birth-date">Date de naissance</Label>
                <Input
                  id="pqc-birth-date"
                  type="date"
                  value={form.birth_date}
                  onChange={(e) => setForm((f) => ({ ...f, birth_date: e.target.value }))}
                />
                <FieldError>{fieldErrors.birth_date}</FieldError>
              </div>
            </div>

            <div>
              <Label htmlFor="pqc-phone">Téléphone</Label>
              <Input
                id="pqc-phone"
                value={form.phone}
                onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
              />
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label htmlFor="pqc-ec-name">Contact d'urgence — nom</Label>
                <Input
                  id="pqc-ec-name"
                  value={form.emergency_contact_name}
                  onChange={(e) => setForm((f) => ({ ...f, emergency_contact_name: e.target.value }))}
                />
              </div>
              <div>
                <Label htmlFor="pqc-ec-phone">Contact d'urgence — téléphone</Label>
                <Input
                  id="pqc-ec-phone"
                  value={form.emergency_contact_phone}
                  onChange={(e) => setForm((f) => ({ ...f, emergency_contact_phone: e.target.value }))}
                />
              </div>
            </div>

            <DialogFooter>
              <Button type="button" variant="secondary" onClick={() => handleOpenChange(false)}>
                Annuler
              </Button>
              <Button type="submit" disabled={createPatient.isPending}>
                {createPatient.isPending ? "Création..." : "Créer le patient"}
              </Button>
            </DialogFooter>
          </form>
        )}
      </DialogContent>
    </Dialog>
  );
}
