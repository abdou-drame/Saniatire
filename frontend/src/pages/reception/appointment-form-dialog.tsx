import { useEffect, useState, type FormEvent } from "react";
import { PatientPicker } from "@/components/clinical/patient-picker";
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
import { useCreateAppointment, useUpdateAppointment } from "@/hooks/use-appointments";
import { usePatientsDirectory } from "@/hooks/use-patients-directory";
import { usePractitioners } from "@/hooks/use-practitioners";
import { useSites } from "@/hooks/use-sites";
import { apiErrorMessage } from "@/lib/api-error";
import type { Appointment, Patient } from "@/types/api";
import { PatientQuickCreateDialog } from "@/pages/reception/patient-quick-create-dialog";

export interface AppointmentFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  siteId: number;
  /** Present when rescheduling/editing an existing appointment; absent when creating one. */
  appointment?: Appointment | null;
  defaultStartsAt?: string;
  /** When set, the patient is fixed and shown read-only instead of the picker — e.g. scheduling a home-care visit from the patient's own record. */
  lockedPatient?: Patient | null;
}

function toDatetimeLocal(iso?: string): string {
  const date = iso ? new Date(iso) : new Date();
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function AppointmentFormDialog({
  open,
  onOpenChange,
  siteId,
  appointment = null,
  defaultStartsAt,
  lockedPatient = null,
}: AppointmentFormDialogProps) {
  const isEditing = Boolean(appointment);
  const practitioners = usePractitioners();
  const sites = useSites();
  const directory = usePatientsDirectory();
  const createAppointment = useCreateAppointment();
  const updateAppointment = useUpdateAppointment();

  const [patient, setPatient] = useState<Patient | null>(null);
  const [practitionerId, setPractitionerId] = useState<string>("");
  const [formSiteId, setFormSiteId] = useState<string>(String(siteId));
  const [startsAt, setStartsAt] = useState(toDatetimeLocal(defaultStartsAt));
  const [durationMinutes, setDurationMinutes] = useState(30);
  const [reason, setReason] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [quickCreateOpen, setQuickCreateOpen] = useState(false);

  useEffect(() => {
    if (!open) return;
    setSubmitError(null);
    setFieldErrors({});
    if (appointment) {
      setPractitionerId(String(appointment.practitioner_id));
      setFormSiteId(String(appointment.site_id));
      setStartsAt(toDatetimeLocal(appointment.starts_at));
      setDurationMinutes(appointment.duration_minutes);
      setReason(appointment.reason ?? "");
      setPatient(directory.byId.get(appointment.patient_id) ?? null);
    } else {
      setPatient(lockedPatient);
      setPractitionerId("");
      setFormSiteId(String(siteId));
      setStartsAt(toDatetimeLocal(defaultStartsAt));
      setDurationMinutes(30);
      setReason("");
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, appointment, lockedPatient]);

  function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!isEditing && !lockedPatient && !patient) errors.patient = "Sélectionnez un patient.";
    if (!practitionerId) errors.practitioner = "Sélectionnez un praticien.";
    if (!formSiteId) errors.site = "Sélectionnez un site.";
    if (!startsAt) errors.startsAt = "La date et l'heure sont obligatoires.";
    if (!durationMinutes || durationMinutes < 5 || durationMinutes > 480) {
      errors.duration = "La durée doit être comprise entre 5 et 480 minutes.";
    }
    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitError(null);
    if (!validate()) return;

    const startsAtIso = new Date(startsAt).toISOString();

    try {
      if (isEditing && appointment) {
        await updateAppointment.mutateAsync({
          id: appointment.id,
          site_id: Number(formSiteId),
          practitioner_id: Number(practitionerId),
          starts_at: startsAtIso,
          duration_minutes: durationMinutes,
          reason: reason.trim() || undefined,
        });
      } else if (patient) {
        await createAppointment.mutateAsync({
          site_id: Number(formSiteId),
          patient_id: patient.id,
          practitioner_id: Number(practitionerId),
          starts_at: startsAtIso,
          duration_minutes: durationMinutes,
          reason: reason.trim() || undefined,
        });
      }
      onOpenChange(false);
    } catch (error) {
      setSubmitError(apiErrorMessage(error));
    }
  }

  const isPending = createAppointment.isPending || updateAppointment.isPending;

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{isEditing ? "Reporter le rendez-vous" : "Nouveau rendez-vous"}</DialogTitle>
            <DialogDescription>
              {isEditing
                ? "Modifiez le créneau — la disponibilité du praticien est revérifiée par le serveur."
                : "Le créneau est validé côté serveur (chevauchements, planning du praticien)."}
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handleSubmit} className="space-y-4">
            {submitError && (
              <p className="rounded-md border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">{submitError}</p>
            )}

            {!isEditing && !lockedPatient && (
              <div>
                <Label>Patient</Label>
                <PatientPicker value={patient} onChange={setPatient} onRequestCreate={() => setQuickCreateOpen(true)} />
                <FieldError>{fieldErrors.patient}</FieldError>
              </div>
            )}

            {(isEditing || lockedPatient) && patient && (
              <div>
                <Label>Patient</Label>
                <p className="rounded-md border border-border bg-surface px-3 py-2 text-sm text-text">
                  {patient.first_name} {patient.last_name}{" "}
                  <span className="font-tabular text-xs text-text-subtle">{patient.patient_number}</span>
                </p>
              </div>
            )}

            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label htmlFor="apt-practitioner">Praticien</Label>
                <Select
                  id="apt-practitioner"
                  value={practitionerId}
                  onChange={(e) => setPractitionerId(e.target.value)}
                >
                  <option value="">Sélectionner...</option>
                  {(practitioners.data ?? []).map((p) => (
                    <option key={p.id} value={p.id}>
                      Dr {p.first_name} {p.last_name}
                    </option>
                  ))}
                </Select>
                <FieldError>{fieldErrors.practitioner}</FieldError>
              </div>
              <div>
                <Label htmlFor="apt-site">Site</Label>
                <Select id="apt-site" value={formSiteId} onChange={(e) => setFormSiteId(e.target.value)}>
                  <option value="">Sélectionner...</option>
                  {(sites.data ?? []).map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.name}
                    </option>
                  ))}
                </Select>
                <FieldError>{fieldErrors.site}</FieldError>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label htmlFor="apt-starts-at">Date et heure</Label>
                <Input
                  id="apt-starts-at"
                  type="datetime-local"
                  value={startsAt}
                  onChange={(e) => setStartsAt(e.target.value)}
                />
                <FieldError>{fieldErrors.startsAt}</FieldError>
              </div>
              <div>
                <Label htmlFor="apt-duration">Durée (minutes)</Label>
                <Input
                  id="apt-duration"
                  type="number"
                  min={5}
                  max={480}
                  step={5}
                  value={durationMinutes}
                  onChange={(e) => setDurationMinutes(Number(e.target.value))}
                />
                <FieldError>{fieldErrors.duration}</FieldError>
              </div>
            </div>

            <div>
              <Label htmlFor="apt-reason">Motif</Label>
              <Input id="apt-reason" value={reason} onChange={(e) => setReason(e.target.value)} />
            </div>

            <DialogFooter>
              <Button type="button" variant="secondary" onClick={() => onOpenChange(false)}>
                Annuler
              </Button>
              <Button type="submit" disabled={isPending}>
                {isPending ? "Enregistrement..." : isEditing ? "Reporter" : "Créer le rendez-vous"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <PatientQuickCreateDialog
        open={quickCreateOpen}
        onOpenChange={setQuickCreateOpen}
        onPatientReady={(p) => setPatient(p)}
      />
    </>
  );
}
