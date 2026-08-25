import { BedDouble, ClipboardList, LoaderCircle, LogOut } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/loading-state";
import { useAuth } from "@/hooks/use-auth";
import { useAddDailyNote, useDischargePatient, useHospitalizations } from "@/hooks/use-hospitalizations";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate, formatDateTime } from "@/lib/datetime";
import { HOSPITALIZATION_STATUS_BADGE, HOSPITALIZATION_STATUS_LABEL } from "@/pages/hospitalisation/hospitalisation-status";

/**
 * Panneau d'hospitalisation embarqué dans la fiche patient : affiche
 * l'hospitalisation active (le cas échéant) avec ses notes de suivi et
 * l'action de sortie. Après sortie, on refetch la liste plutôt que de
 * modifier l'état local de façon optimiste — le lit ne redevient "libre"
 * dans l'UI qu'une fois le backend l'ayant confirmé.
 */
export function PatientHospitalizationPanel({ patientId }: { patientId: number }) {
  const { hasPermission } = useAuth();
  const hospitalizationsQuery = useHospitalizations({ patientId });

  if (!hasPermission("hospitalisation.view")) return null;

  if (hospitalizationsQuery.isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Hospitalisation</CardTitle>
        </CardHeader>
        <CardContent>
          <Skeleton className="h-24 w-full" />
        </CardContent>
      </Card>
    );
  }

  if (hospitalizationsQuery.isError) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Hospitalisation</CardTitle>
        </CardHeader>
        <CardContent>
          <ErrorState
            message={apiErrorMessage(hospitalizationsQuery.error)}
            onRetry={() => hospitalizationsQuery.refetch()}
          />
        </CardContent>
      </Card>
    );
  }

  const hospitalizations = hospitalizationsQuery.data?.data ?? [];
  const active = hospitalizations.find((h) => h.status === "en_cours") ?? null;
  const past = hospitalizations.filter((h) => h.status !== "en_cours");

  return (
    <Card>
      <CardHeader>
        <CardTitle>Hospitalisation</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {!active && past.length === 0 && (
          <EmptyState
            icon={BedDouble}
            title="Aucune hospitalisation"
            description="Ce patient n'a jamais été hospitalisé."
          />
        )}

        {active && <ActiveHospitalizationCard patientId={patientId} hospitalizationId={active.id} />}

        {past.length > 0 && (
          <div className="space-y-2">
            <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Hospitalisations passées</p>
            {past.map((h) => (
              <div
                key={h.id}
                className="flex items-center justify-between gap-3 rounded-md border border-border bg-surface px-3 py-2 text-xs text-text-muted"
              >
                <span>
                  {h.ward?.name ?? "—"} · {formatDate(h.admitted_at)}
                  {h.discharged_at ? ` → ${formatDate(h.discharged_at)}` : ""}
                </span>
                <Badge status={HOSPITALIZATION_STATUS_BADGE[h.status]}>{HOSPITALIZATION_STATUS_LABEL[h.status]}</Badge>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}

function ActiveHospitalizationCard({ patientId, hospitalizationId }: { patientId: number; hospitalizationId: number }) {
  const { hasPermission } = useAuth();
  const hospitalizationsQuery = useHospitalizations({ patientId });
  const addDailyNote = useAddDailyNote();
  const dischargePatient = useDischargePatient();

  const [careAdministered, setCareAdministered] = useState("");
  const [medicationsGiven, setMedicationsGiven] = useState("");
  const [proceduresPerformed, setProceduresPerformed] = useState("");
  const [observations, setObservations] = useState("");
  const [noteError, setNoteError] = useState<string | null>(null);

  const [dischargeSummary, setDischargeSummary] = useState("");
  const [dischargeDialogOpen, setDischargeDialogOpen] = useState(false);
  const [dischargeError, setDischargeError] = useState<string | null>(null);

  const hospitalization = hospitalizationsQuery.data?.data.find((h) => h.id === hospitalizationId) ?? null;
  if (!hospitalization) return null;

  const canDailyNote = hasPermission("hospitalisation.daily_note");
  const canDischarge = hasPermission("hospitalisation.update");

  function handleAddNote() {
    setNoteError(null);
    addDailyNote.mutate(
      {
        hospitalizationId,
        care_administered: careAdministered.trim() || undefined,
        medications_given: medicationsGiven.trim() || undefined,
        procedures_performed: proceduresPerformed.trim() || undefined,
        observations: observations.trim() || undefined,
      },
      {
        onSuccess: () => {
          setCareAdministered("");
          setMedicationsGiven("");
          setProceduresPerformed("");
          setObservations("");
        },
        onError: (err) => setNoteError(apiErrorMessage(err)),
      },
    );
  }

  function handleDischarge() {
    setDischargeError(null);
    dischargePatient.mutate(
      { hospitalizationId, discharge_summary: dischargeSummary.trim() },
      {
        onSuccess: () => {
          setDischargeDialogOpen(false);
          setDischargeSummary("");
          hospitalizationsQuery.refetch();
        },
        onError: (err) => setDischargeError(apiErrorMessage(err)),
      },
    );
  }

  const canSubmitNote =
    careAdministered.trim() || medicationsGiven.trim() || proceduresPerformed.trim() || observations.trim();

  return (
    <div className="space-y-4 rounded-md border border-border p-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-text">
            {hospitalization.ward?.name ?? "—"} · Lit{" "}
            {hospitalization.bed ? `${hospitalization.bed.room_number} — ${hospitalization.bed.bed_label}` : "—"}
          </p>
          <p className="text-xs text-text-muted">
            Admis le {formatDateTime(hospitalization.admitted_at)} · {hospitalization.attending_physician_label ?? "—"}
          </p>
        </div>
        <Badge status={HOSPITALIZATION_STATUS_BADGE[hospitalization.status]}>
          {HOSPITALIZATION_STATUS_LABEL[hospitalization.status]}
        </Badge>
      </div>

      <div className="space-y-2">
        <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Suivi quotidien</p>
        {hospitalization.daily_notes.length === 0 ? (
          <p className="text-xs text-text-subtle">Aucune note de suivi enregistrée.</p>
        ) : (
          <ol className="space-y-2">
            {hospitalization.daily_notes.map((note) => (
              <li key={note.id} className="rounded-md border border-border bg-surface px-3 py-2 text-xs text-text-muted">
                <p className="mb-1 font-medium text-text">{formatDate(note.note_date)}</p>
                {note.care_administered && <p>Soins : {note.care_administered}</p>}
                {note.medications_given && <p>Médicaments : {note.medications_given}</p>}
                {note.procedures_performed && <p>Actes : {note.procedures_performed}</p>}
                {note.observations && <p>Observations : {note.observations}</p>}
              </li>
            ))}
          </ol>
        )}

        {canDailyNote && (
          <div className="space-y-2 rounded-md border border-dashed border-border p-3">
            <div>
              <Label>Soins administrés</Label>
              <textarea
                value={careAdministered}
                onChange={(e) => setCareAdministered(e.target.value)}
                rows={2}
                className="w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
              />
            </div>
            <div>
              <Label>Médicaments administrés</Label>
              <textarea
                value={medicationsGiven}
                onChange={(e) => setMedicationsGiven(e.target.value)}
                rows={2}
                className="w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
              />
            </div>
            <div>
              <Label>Actes réalisés</Label>
              <textarea
                value={proceduresPerformed}
                onChange={(e) => setProceduresPerformed(e.target.value)}
                rows={2}
                className="w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
              />
            </div>
            <div>
              <Label>Observations</Label>
              <textarea
                value={observations}
                onChange={(e) => setObservations(e.target.value)}
                rows={2}
                className="w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
              />
            </div>
            {noteError && (
              <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
                {noteError}
              </p>
            )}
            <Button size="sm" onClick={handleAddNote} disabled={!canSubmitNote || addDailyNote.isPending}>
              {addDailyNote.isPending && <LoaderCircle size={14} className="animate-spin" />}
              <ClipboardList size={14} />
              Ajouter la note
            </Button>
          </div>
        )}
      </div>

      {canDischarge && (
        <Button variant="secondary" size="sm" onClick={() => setDischargeDialogOpen(true)}>
          <LogOut size={14} />
          Sortie du patient
        </Button>
      )}

      <Dialog open={dischargeDialogOpen} onOpenChange={setDischargeDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Sortie du patient</DialogTitle>
            <DialogDescription>Cette action clôture l'hospitalisation et libère le lit.</DialogDescription>
          </DialogHeader>
          <div className="space-y-3">
            <div>
              <Label>Résumé de sortie</Label>
              <textarea
                value={dischargeSummary}
                onChange={(e) => setDischargeSummary(e.target.value)}
                rows={3}
                className="w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                placeholder="Résumé clinique de sortie..."
              />
            </div>
            {dischargeError && (
              <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
                {dischargeError}
              </p>
            )}
          </div>
          <DialogFooter>
            <Button variant="secondary" onClick={() => setDischargeDialogOpen(false)} disabled={dischargePatient.isPending}>
              Annuler
            </Button>
            <Button onClick={handleDischarge} disabled={!dischargeSummary.trim() || dischargePatient.isPending}>
              {dischargePatient.isPending && <LoaderCircle size={16} className="animate-spin" />}
              Confirmer la sortie
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
