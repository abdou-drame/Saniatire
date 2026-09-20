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
import { useUpdatePatient } from "@/hooks/use-update-patient";
import { apiErrorMessage } from "@/lib/api-error";
import type { Patient } from "@/types/api";

export interface PatientEditDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  patient: Patient;
}

interface FormState {
  first_name: string;
  last_name: string;
  sex: "M" | "F";
  birth_date: string;
  phone: string;
  email: string;
  address: string;
  profession: string;
  nationality: string;
  emergency_contact_name: string;
  emergency_contact_phone: string;
  emergency_contact_relationship: string;
}

// Same permissiveness as the backend's `email` validation rule — this is a
// UX shortcut to catch obvious typos before the round trip, not a substitute
// for the server-side check.
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function toForm(patient: Patient): FormState {
  return {
    first_name: patient.first_name,
    last_name: patient.last_name,
    sex: patient.sex,
    birth_date: patient.birth_date.slice(0, 10),
    phone: patient.phone ?? "",
    email: patient.email ?? "",
    address: patient.address ?? "",
    profession: patient.profession ?? "",
    nationality: patient.nationality ?? "",
    emergency_contact_name: patient.emergency_contact_name ?? "",
    emergency_contact_phone: patient.emergency_contact_phone ?? "",
    emergency_contact_relationship: patient.emergency_contact_relationship ?? "",
  };
}

export function PatientEditDialog({ open, onOpenChange, patient }: PatientEditDialogProps) {
  const [form, setForm] = useState<FormState>(() => toForm(patient));
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState<string | null>(null);
  const updatePatient = useUpdatePatient(patient.id);

  // Re-sync if the dialog is reopened for the same or a different patient
  // (or after the parent's query refetches with the saved values).
  useEffect(() => {
    if (open) {
      setForm(toForm(patient));
      setFieldErrors({});
      setSubmitError(null);
    }
  }, [open, patient]);

  function handleOpenChange(next: boolean) {
    if (!next) updatePatient.reset();
    onOpenChange(next);
  }

  function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!form.first_name.trim()) errors.first_name = "Le prénom est obligatoire.";
    if (!form.last_name.trim()) errors.last_name = "Le nom est obligatoire.";
    if (!form.birth_date) errors.birth_date = "La date de naissance est obligatoire.";
    else if (new Date(form.birth_date) > new Date()) errors.birth_date = "La date de naissance ne peut pas être future.";
    if (form.email.trim() && !EMAIL_PATTERN.test(form.email.trim())) {
      errors.email = "Adresse email invalide.";
    }
    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitError(null);
    if (!validate()) return;

    try {
      await updatePatient.mutateAsync({
        first_name: form.first_name.trim(),
        last_name: form.last_name.trim(),
        sex: form.sex,
        birth_date: form.birth_date,
        phone: form.phone.trim() || null,
        email: form.email.trim() || null,
        address: form.address.trim() || null,
        profession: form.profession.trim() || null,
        nationality: form.nationality.trim() || null,
        emergency_contact_name: form.emergency_contact_name.trim() || null,
        emergency_contact_phone: form.emergency_contact_phone.trim() || null,
        emergency_contact_relationship: form.emergency_contact_relationship.trim() || null,
      });
      handleOpenChange(false);
    } catch (error) {
      setSubmitError(apiErrorMessage(error));
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Modifier la fiche patient</DialogTitle>
          <DialogDescription>Identité et coordonnées du patient.</DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          {submitError && (
            <p className="rounded-md border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">
              {submitError}
            </p>
          )}

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label htmlFor="pe-first-name">Prénom</Label>
              <Input
                id="pe-first-name"
                value={form.first_name}
                onChange={(e) => setForm((f) => ({ ...f, first_name: e.target.value }))}
              />
              <FieldError>{fieldErrors.first_name}</FieldError>
            </div>
            <div>
              <Label htmlFor="pe-last-name">Nom</Label>
              <Input
                id="pe-last-name"
                value={form.last_name}
                onChange={(e) => setForm((f) => ({ ...f, last_name: e.target.value }))}
              />
              <FieldError>{fieldErrors.last_name}</FieldError>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label htmlFor="pe-sex">Sexe</Label>
              <Select
                id="pe-sex"
                value={form.sex}
                onChange={(e) => setForm((f) => ({ ...f, sex: e.target.value as "M" | "F" }))}
              >
                <option value="M">Masculin</option>
                <option value="F">Féminin</option>
              </Select>
            </div>
            <div>
              <Label htmlFor="pe-birth-date">Date de naissance</Label>
              <Input
                id="pe-birth-date"
                type="date"
                value={form.birth_date}
                onChange={(e) => setForm((f) => ({ ...f, birth_date: e.target.value }))}
              />
              <FieldError>{fieldErrors.birth_date}</FieldError>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label htmlFor="pe-phone">Téléphone</Label>
              <Input
                id="pe-phone"
                value={form.phone}
                onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
              />
            </div>
            <div>
              <Label htmlFor="pe-email">Email</Label>
              <Input
                id="pe-email"
                type="text"
                inputMode="email"
                value={form.email}
                onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
              />
              <FieldError>{fieldErrors.email}</FieldError>
            </div>
          </div>

          <div>
            <Label htmlFor="pe-address">Adresse</Label>
            <Input
              id="pe-address"
              value={form.address}
              onChange={(e) => setForm((f) => ({ ...f, address: e.target.value }))}
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label htmlFor="pe-profession">Profession</Label>
              <Input
                id="pe-profession"
                value={form.profession}
                onChange={(e) => setForm((f) => ({ ...f, profession: e.target.value }))}
              />
            </div>
            <div>
              <Label htmlFor="pe-nationality">Nationalité</Label>
              <Input
                id="pe-nationality"
                value={form.nationality}
                onChange={(e) => setForm((f) => ({ ...f, nationality: e.target.value }))}
              />
            </div>
          </div>

          <div className="grid grid-cols-3 gap-3">
            <div>
              <Label htmlFor="pe-ec-name">Contact d'urgence — nom</Label>
              <Input
                id="pe-ec-name"
                value={form.emergency_contact_name}
                onChange={(e) => setForm((f) => ({ ...f, emergency_contact_name: e.target.value }))}
              />
            </div>
            <div>
              <Label htmlFor="pe-ec-phone">Contact d'urgence — téléphone</Label>
              <Input
                id="pe-ec-phone"
                value={form.emergency_contact_phone}
                onChange={(e) => setForm((f) => ({ ...f, emergency_contact_phone: e.target.value }))}
              />
            </div>
            <div>
              <Label htmlFor="pe-ec-relationship">Contact d'urgence — lien</Label>
              <Input
                id="pe-ec-relationship"
                value={form.emergency_contact_relationship}
                onChange={(e) => setForm((f) => ({ ...f, emergency_contact_relationship: e.target.value }))}
              />
            </div>
          </div>

          <DialogFooter>
            <Button type="button" variant="secondary" onClick={() => handleOpenChange(false)}>
              Annuler
            </Button>
            <Button type="submit" disabled={updatePatient.isPending}>
              {updatePatient.isPending ? "Enregistrement..." : "Enregistrer"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
