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
import { useAppointments } from "@/hooks/use-appointments";
import { usePatientsDirectory } from "@/hooks/use-patients-directory";
import { usePractitioners } from "@/hooks/use-practitioners";
import { useRegisterArrival } from "@/hooks/use-queue-entries";
import { apiErrorMessage } from "@/lib/api-error";
import { cn } from "@/lib/utils";
import type { Patient, QueueEntry, QueuePriority } from "@/types/api";
import { PatientQuickCreateDialog } from "@/pages/reception/patient-quick-create-dialog";

export interface CheckInDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  siteId: number;
  todaysQueue: QueueEntry[];
}

function todayKey(): string {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

export function CheckInDialog({ open, onOpenChange, siteId, todaysQueue }: CheckInDialogProps) {
  const [mode, setMode] = useState<"rdv" | "walk_in">("rdv");
  const [selectedAppointmentId, setSelectedAppointmentId] = useState<number | null>(null);
  const [patient, setPatient] = useState<Patient | null>(null);
  const [practitionerId, setPractitionerId] = useState<string>("");
  const [service, setService] = useState("");
  const [priority, setPriority] = useState<QueuePriority>("normale");
  const [quickCreateOpen, setQuickCreateOpen] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [fieldError, setFieldError] = useState<string | null>(null);

  const directory = usePatientsDirectory();
  const practitioners = usePractitioners();
  const registerArrival = useRegisterArrival();

  const day = todayKey();
  const appointmentsQuery = useAppointments({ from: day, to: day, siteId });

  const alreadyCheckedInAppointmentIds = new Set(
    todaysQueue.map((e) => e.appointment_id).filter((id): id is number => id !== null),
  );
  const pendingAppointments = (appointmentsQuery.data ?? []).filter(
    (a) => (a.status === "planifie" || a.status === "confirme") && !alreadyCheckedInAppointmentIds.has(a.id),
  );

  useEffect(() => {
    if (!open) return;
    setMode("rdv");
    setSelectedAppointmentId(null);
    setPatient(null);
    setPractitionerId("");
    setService("");
    setPriority("normale");
    setSubmitError(null);
    setFieldError(null);
  }, [open]);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitError(null);
    setFieldError(null);

    if (mode === "rdv") {
      const appointment = pendingAppointments.find((a) => a.id === selectedAppointmentId);
      if (!appointment) {
        setFieldError("Sélectionnez un rendez-vous.");
        return;
      }
      try {
        await registerArrival.mutateAsync({
          site_id: siteId,
          patient_id: appointment.patient_id,
          appointment_id: appointment.id,
          practitioner_id: appointment.practitioner_id,
          service: appointment.reason || "Consultation",
        });
        onOpenChange(false);
      } catch (error) {
        setSubmitError(apiErrorMessage(error));
      }
      return;
    }

    if (!patient) {
      setFieldError("Sélectionnez ou créez un patient.");
      return;
    }
    if (!service.trim()) {
      setFieldError("Le service est obligatoire.");
      return;
    }
    try {
      await registerArrival.mutateAsync({
        site_id: siteId,
        patient_id: patient.id,
        appointment_id: null,
        practitioner_id: practitionerId ? Number(practitionerId) : null,
        service: service.trim(),
        priority,
      });
      onOpenChange(false);
    } catch (error) {
      setSubmitError(apiErrorMessage(error));
    }
  }

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Enregistrer l'arrivée</DialogTitle>
            <DialogDescription>Ajoute le patient à la file d'attente du site.</DialogDescription>
          </DialogHeader>

          <div className="mb-4 flex rounded-md border border-border p-0.5">
            {(["rdv", "walk_in"] as const).map((m) => (
              <button
                key={m}
                type="button"
                onClick={() => setMode(m)}
                className={cn(
                  "flex-1 rounded px-3 py-1.5 text-xs font-medium transition-colors",
                  mode === m ? "bg-accent text-white" : "text-text-muted hover:bg-surface-hover",
                )}
              >
                {m === "rdv" ? "Avec rendez-vous" : "Sans rendez-vous"}
              </button>
            ))}
          </div>

          <form onSubmit={handleSubmit} className="space-y-4">
            {submitError && (
              <p className="rounded-md border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">{submitError}</p>
            )}

            {mode === "rdv" ? (
              <div>
                <Label>Rendez-vous du jour</Label>
                {appointmentsQuery.isLoading ? (
                  <p className="text-sm text-text-muted">Chargement…</p>
                ) : pendingAppointments.length === 0 ? (
                  <p className="rounded-md border border-dashed border-border px-3 py-4 text-center text-sm text-text-muted">
                    Aucun rendez-vous en attente d'arrivée aujourd'hui pour ce site.
                  </p>
                ) : (
                  <div className="max-h-64 space-y-1.5 overflow-y-auto">
                    {pendingAppointments.map((appointment) => {
                      const p = directory.byId.get(appointment.patient_id);
                      const isSelected = selectedAppointmentId === appointment.id;
                      return (
                        <button
                          key={appointment.id}
                          type="button"
                          onClick={() => setSelectedAppointmentId(appointment.id)}
                          className={cn(
                            "flex w-full items-center justify-between gap-3 rounded-md border px-3 py-2 text-left text-sm",
                            isSelected ? "border-accent bg-accent/10" : "border-border bg-surface hover:bg-surface-hover",
                          )}
                        >
                          <span className="text-text">
                            {p ? `${p.first_name} ${p.last_name}` : `Patient #${appointment.patient_id}`}
                          </span>
                          <span className="font-tabular text-xs text-text-subtle">
                            {new Date(appointment.starts_at).toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" })}
                          </span>
                        </button>
                      );
                    })}
                  </div>
                )}
                <FieldError>{fieldError ?? undefined}</FieldError>
              </div>
            ) : (
              <>
                <div>
                  <Label>Patient</Label>
                  <PatientPicker value={patient} onChange={setPatient} onRequestCreate={() => setQuickCreateOpen(true)} />
                  <FieldError>{fieldError && !patient ? fieldError : undefined}</FieldError>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <Label htmlFor="ci-service">Service</Label>
                    <Input
                      id="ci-service"
                      placeholder="Ex. Médecine générale"
                      value={service}
                      onChange={(e) => setService(e.target.value)}
                    />
                  </div>
                  <div>
                    <Label htmlFor="ci-priority">Priorité</Label>
                    <Select id="ci-priority" value={priority} onChange={(e) => setPriority(e.target.value as QueuePriority)}>
                      <option value="normale">Normale</option>
                      <option value="urgente">Urgente</option>
                      <option value="tres_urgente">Très urgente</option>
                    </Select>
                  </div>
                </div>

                <div>
                  <Label htmlFor="ci-practitioner">Praticien visé (optionnel)</Label>
                  <Select id="ci-practitioner" value={practitionerId} onChange={(e) => setPractitionerId(e.target.value)}>
                    <option value="">Non assigné</option>
                    {(practitioners.data ?? []).map((p) => (
                      <option key={p.id} value={p.id}>
                        Dr {p.first_name} {p.last_name}
                      </option>
                    ))}
                  </Select>
                </div>
              </>
            )}

            <DialogFooter>
              <Button type="button" variant="secondary" onClick={() => onOpenChange(false)}>
                Annuler
              </Button>
              <Button type="submit" disabled={registerArrival.isPending}>
                {registerArrival.isPending ? "Enregistrement..." : "Enregistrer l'arrivée"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <PatientQuickCreateDialog open={quickCreateOpen} onOpenChange={setQuickCreateOpen} onPatientReady={setPatient} />
    </>
  );
}
