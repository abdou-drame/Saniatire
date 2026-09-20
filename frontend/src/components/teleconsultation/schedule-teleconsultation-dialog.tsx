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
import { useAppointments } from "@/hooks/use-appointments";
import { useAuth } from "@/hooks/use-auth";
import { usePatientsDirectory } from "@/hooks/use-patients-directory";
import { usePractitioners } from "@/hooks/use-practitioners";
import { useSites } from "@/hooks/use-sites";
import { useCreateTeleconsultation, useTeleconsultations } from "@/hooks/use-teleconsultations";
import { apiErrorMessage } from "@/lib/api-error";
import { formatTime } from "@/lib/datetime";
import type { Appointment } from "@/types/api";

export interface ScheduleTeleconsultationDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

function pad(n: number): string {
  return String(n).padStart(2, "0");
}

function todayKey(): string {
  const d = new Date();
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/**
 * Sélectionne un rendez-vous EXISTANT à convertir en téléconsultation.
 * GET /appointments ne filtre que sur from/to/practitioner_id/site_id —
 * aucun patient_id (confirmé en lisant AppointmentController::index) — donc
 * ce picker parcourt par date (+ site/praticien optionnels), jamais par
 * patient.
 */
export function ScheduleTeleconsultationDialog({ open, onOpenChange }: ScheduleTeleconsultationDialogProps) {
  const { hasPermission } = useAuth();
  const [date, setDate] = useState(todayKey());
  const [practitionerId, setPractitionerId] = useState<string>("");
  const [siteId, setSiteId] = useState<string>("");
  const [error, setError] = useState<string | null>(null);
  const [creatingAppointmentId, setCreatingAppointmentId] = useState<number | null>(null);

  useEffect(() => {
    if (!open) return;
    setDate(todayKey());
    setPractitionerId("");
    setSiteId("");
    setError(null);
    setCreatingAppointmentId(null);
  }, [open]);

  const appointmentsQuery = useAppointments({
    from: date,
    to: date,
    practitionerId: practitionerId ? Number(practitionerId) : undefined,
    siteId: siteId ? Number(siteId) : undefined,
  });
  const patientsDirectory = usePatientsDirectory();
  const practitioners = usePractitioners();
  // sites.view n'est pas accordé au rôle médecin (cf. RolePermissionSeeder) —
  // sans ce garde, un médecin ouvrant ce dialogue déclenche un 403 inutile
  // sur GET /sites (contrat documenté dans use-sites.ts).
  const sites = useSites(hasPermission("sites.view"));
  // Jeu complet des téléconsultations existantes, pour masquer par confort
  // les rendez-vous déjà convertis. Aucune contrainte d'unicité n'existe
  // côté serveur (TeleconsultationController::store n'en impose aucune) —
  // ce filtre n'est donc qu'une commodité d'affichage, jamais présentée
  // comme une règle imposée par le backend.
  const teleconsultationsQuery = useTeleconsultations();
  const convertedAppointmentIds = new Set((teleconsultationsQuery.data ?? []).map((t) => t.appointment_id));

  const createTeleconsultation = useCreateTeleconsultation();

  function patientLabel(appointment: Appointment): string {
    const patient = patientsDirectory.byId.get(appointment.patient_id);
    return patient ? `${patient.first_name} ${patient.last_name}` : `Patient #${appointment.patient_id}`;
  }

  function practitionerLabel(appointment: Appointment): string {
    const practitioner = (practitioners.data ?? []).find((p) => p.id === appointment.practitioner_id);
    return practitioner
      ? `Dr ${practitioner.first_name} ${practitioner.last_name}`
      : `Praticien #${appointment.practitioner_id}`;
  }

  async function handleSelect(appointment: Appointment) {
    setError(null);
    setCreatingAppointmentId(appointment.id);
    try {
      await createTeleconsultation.mutateAsync({ appointment_id: appointment.id });
      onOpenChange(false);
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setCreatingAppointmentId(null);
    }
  }

  const appointments = appointmentsQuery.data ?? [];

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Nouvelle téléconsultation</DialogTitle>
          <DialogDescription>
            Sélectionnez le rendez-vous existant à convertir en téléconsultation.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="grid grid-cols-3 gap-3">
            <div>
              <Label htmlFor="tc-date">Date</Label>
              <Input id="tc-date" type="date" value={date} onChange={(e) => setDate(e.target.value)} />
            </div>
            <div>
              <Label htmlFor="tc-practitioner">Praticien</Label>
              <Select id="tc-practitioner" value={practitionerId} onChange={(e) => setPractitionerId(e.target.value)}>
                <option value="">Tous</option>
                {(practitioners.data ?? []).map((p) => (
                  <option key={p.id} value={p.id}>
                    Dr {p.first_name} {p.last_name}
                  </option>
                ))}
              </Select>
            </div>
            <div>
              <Label htmlFor="tc-site">Site</Label>
              <Select id="tc-site" value={siteId} onChange={(e) => setSiteId(e.target.value)}>
                <option value="">Tous</option>
                {(sites.data ?? []).map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name}
                  </option>
                ))}
              </Select>
            </div>
          </div>

          {error && (
            <p className="rounded-md border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">{error}</p>
          )}

          {appointmentsQuery.isLoading ? (
            <p className="text-sm text-text-muted">Chargement des rendez-vous…</p>
          ) : appointments.length === 0 ? (
            <p className="rounded-md border border-dashed border-border px-3 py-6 text-center text-sm text-text-muted">
              Aucun rendez-vous pour cette date et ces filtres.
            </p>
          ) : (
            <div className="max-h-72 divide-y divide-border overflow-y-auto rounded-md border border-border">
              {appointments.map((appointment) => {
                const alreadyConverted = convertedAppointmentIds.has(appointment.id);
                const isCreating = creatingAppointmentId === appointment.id;
                return (
                  <button
                    key={appointment.id}
                    type="button"
                    disabled={alreadyConverted || createTeleconsultation.isPending}
                    onClick={() => handleSelect(appointment)}
                    className="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-sm hover:bg-surface-hover disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    <span>
                      <span className="font-medium text-text">{patientLabel(appointment)}</span>
                      <span className="block text-xs text-text-muted">
                        {formatTime(appointment.starts_at)} · {practitionerLabel(appointment)}
                      </span>
                    </span>
                    <span className="text-xs text-text-subtle">
                      {isCreating ? (
                        <LoaderCircle size={14} className="animate-spin" />
                      ) : alreadyConverted ? (
                        "Déjà planifiée"
                      ) : null}
                    </span>
                  </button>
                );
              })}
            </div>
          )}
        </div>

        <DialogFooter>
          <Button type="button" variant="secondary" onClick={() => onOpenChange(false)}>
            Fermer
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
