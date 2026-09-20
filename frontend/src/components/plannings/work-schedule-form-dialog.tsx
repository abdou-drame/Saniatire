import { useEffect, useState, type FormEvent } from "react";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { FieldError, Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { useCreateWorkSchedule, useDeleteWorkSchedule, useUpdateWorkSchedule } from "@/hooks/use-work-schedules";
import { useSites } from "@/hooks/use-sites";
import { apiErrorMessage } from "@/lib/api-error";
import { cn } from "@/lib/utils";
import { WEEKDAY_OPTIONS, WORK_SCHEDULE_TYPE_LABEL } from "@/pages/plannings/plannings-status";
import type { WorkSchedule, WorkScheduleType } from "@/types/api";

type RecurrenceMode = "recurrent" | "ponctuel";

const TYPE_OPTIONS: WorkScheduleType[] = ["normal", "garde", "astreinte"];

export interface WorkScheduleFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Praticien ciblé — verrouillé, non re-sélectionnable dans ce formulaire. */
  userId: number;
  /** Présent en mode édition, absent en création. */
  schedule?: WorkSchedule | null;
  canDelete?: boolean;
}

function todayDateInput(): string {
  const d = new Date();
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/**
 * Dialogue de création/édition d'un WorkSchedule pour un praticien fixé.
 * Le toggle "Récurrent"/"Ponctuel" n'est qu'un confort d'UX pour ne
 * soumettre que `jour_semaine` OU `date` : la validation XOR réelle est
 * faite côté backend (WorkScheduleRequest) et sa 422 est affichée telle
 * quelle via apiErrorMessage en cas d'incohérence.
 */
export function WorkScheduleFormDialog({ open, onOpenChange, userId, schedule = null, canDelete = false }: WorkScheduleFormDialogProps) {
  const isEditing = Boolean(schedule);
  const sites = useSites();
  const createSchedule = useCreateWorkSchedule();
  const updateSchedule = useUpdateWorkSchedule();
  const deleteSchedule = useDeleteWorkSchedule();

  const [mode, setMode] = useState<RecurrenceMode>("recurrent");
  const [jourSemaine, setJourSemaine] = useState<string>("1");
  const [date, setDate] = useState<string>(todayDateInput());
  const [siteId, setSiteId] = useState<string>("");
  const [heureDebut, setHeureDebut] = useState("08:00");
  const [heureFin, setHeureFin] = useState("17:00");
  const [type, setType] = useState<WorkScheduleType>("normal");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [confirmingDelete, setConfirmingDelete] = useState(false);

  useEffect(() => {
    if (!open) return;
    setSubmitError(null);
    setFieldErrors({});
    setConfirmingDelete(false);
    if (schedule) {
      setMode(schedule.date ? "ponctuel" : "recurrent");
      setJourSemaine(schedule.jour_semaine !== null ? String(schedule.jour_semaine) : "1");
      setDate(schedule.date ?? todayDateInput());
      setSiteId(String(schedule.site_id));
      setHeureDebut(schedule.heure_debut.slice(0, 5));
      setHeureFin(schedule.heure_fin.slice(0, 5));
      setType(schedule.type);
    } else {
      setMode("recurrent");
      setJourSemaine("1");
      setDate(todayDateInput());
      setSiteId("");
      setHeureDebut("08:00");
      setHeureFin("17:00");
      setType("normal");
    }
  }, [open, schedule]);

  function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!siteId) errors.site = "Sélectionnez un site.";
    if (!heureDebut) errors.heureDebut = "L'heure de début est obligatoire.";
    if (!heureFin) errors.heureFin = "L'heure de fin est obligatoire.";
    if (mode === "ponctuel" && !date) errors.date = "La date est obligatoire.";
    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitError(null);
    if (!validate()) return;

    const basePayload = {
      user_id: userId,
      site_id: Number(siteId),
      heure_debut: heureDebut,
      heure_fin: heureFin,
      type,
      // Toujours envoyer explicitement `null` (jamais `undefined`, que axios
      // supprimerait du corps de la requête) sur le champ non retenu : en
      // édition, WorkScheduleController::update() ne touche que les clés
      // présentes dans le payload (`$workSchedule->update($validated)`), donc
      // omettre jour_semaine/date laisserait une valeur périmée en base si
      // l'horaire édité provenait de l'autre mode (ex. modifier une
      // occurrence d'un horaire récurrent sans effacer son jour_semaine).
      jour_semaine: mode === "recurrent" ? Number(jourSemaine) : null,
      date: mode === "ponctuel" ? date : null,
    };

    try {
      if (isEditing && schedule) {
        await updateSchedule.mutateAsync({ id: schedule.id, ...basePayload });
      } else {
        await createSchedule.mutateAsync(basePayload);
      }
      onOpenChange(false);
    } catch (error) {
      setSubmitError(apiErrorMessage(error));
    }
  }

  function handleDelete() {
    if (!schedule) return;
    deleteSchedule.mutate(schedule.id, {
      onSuccess: () => {
        setConfirmingDelete(false);
        onOpenChange(false);
      },
      onError: (error) => {
        setConfirmingDelete(false);
        setSubmitError(apiErrorMessage(error));
      },
    });
  }

  const isPending = createSchedule.isPending || updateSchedule.isPending;

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{isEditing ? "Modifier l'horaire" : "Nouvel horaire"}</DialogTitle>
            <DialogDescription>
              {isEditing
                ? "Modifiez ce créneau de planning."
                : "Créez un créneau récurrent (jour de semaine) ou ponctuel (date précise) pour ce praticien."}
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handleSubmit} className="space-y-4">
            {submitError && (
              <p className="rounded-md border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">{submitError}</p>
            )}

            <div className="flex rounded-md border border-border p-0.5">
              {(
                [
                  { value: "recurrent", label: "Récurrent (jour de la semaine)" },
                  { value: "ponctuel", label: "Ponctuel (date précise)" },
                ] as const
              ).map((option) => (
                <button
                  key={option.value}
                  type="button"
                  onClick={() => setMode(option.value)}
                  className={cn(
                    "flex-1 rounded px-3 py-1.5 text-xs font-medium transition-colors",
                    mode === option.value ? "bg-accent text-white" : "text-text-muted hover:bg-surface-hover",
                  )}
                >
                  {option.label}
                </button>
              ))}
            </div>

            {mode === "recurrent" ? (
              <div>
                <Label htmlFor="ws-jour-semaine">Jour de la semaine</Label>
                <Select id="ws-jour-semaine" value={jourSemaine} onChange={(e) => setJourSemaine(e.target.value)}>
                  {WEEKDAY_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </Select>
              </div>
            ) : (
              <div>
                <Label htmlFor="ws-date">Date</Label>
                <Input id="ws-date" type="date" value={date} onChange={(e) => setDate(e.target.value)} />
                <FieldError>{fieldErrors.date}</FieldError>
              </div>
            )}

            <div>
              <Label htmlFor="ws-site">Site</Label>
              <Select id="ws-site" value={siteId} onChange={(e) => setSiteId(e.target.value)} disabled={sites.isLoading}>
                <option value="">Sélectionner...</option>
                {(sites.data ?? []).map((site) => (
                  <option key={site.id} value={site.id}>
                    {site.name}
                  </option>
                ))}
              </Select>
              {sites.isError && (
                <p className="mt-1 text-xs text-danger">{apiErrorMessage(sites.error)}</p>
              )}
              {!sites.isLoading && !sites.isError && (sites.data ?? []).length === 0 && (
                <p className="mt-1 text-xs text-text-muted">
                  Aucun site n'est encore enregistré pour votre structure. Ajoutez-en un dans « Structures &amp; sites ».
                </p>
              )}
              <FieldError>{fieldErrors.site}</FieldError>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label htmlFor="ws-heure-debut">Heure de début</Label>
                <Input id="ws-heure-debut" type="time" value={heureDebut} onChange={(e) => setHeureDebut(e.target.value)} />
                <FieldError>{fieldErrors.heureDebut}</FieldError>
              </div>
              <div>
                <Label htmlFor="ws-heure-fin">Heure de fin</Label>
                <Input id="ws-heure-fin" type="time" value={heureFin} onChange={(e) => setHeureFin(e.target.value)} />
                <FieldError>{fieldErrors.heureFin}</FieldError>
              </div>
            </div>

            <div>
              <Label htmlFor="ws-type">Type</Label>
              <Select id="ws-type" value={type} onChange={(e) => setType(e.target.value as WorkScheduleType)}>
                {TYPE_OPTIONS.map((option) => (
                  <option key={option} value={option}>
                    {WORK_SCHEDULE_TYPE_LABEL[option]}
                  </option>
                ))}
              </Select>
            </div>

            <DialogFooter className={cn(isEditing && canDelete && "sm:justify-between")}>
              {isEditing && canDelete && (
                <Button
                  type="button"
                  variant="danger"
                  className="mr-auto"
                  onClick={() => setConfirmingDelete(true)}
                  disabled={isPending || deleteSchedule.isPending}
                >
                  Supprimer
                </Button>
              )}
              <div className="flex gap-2">
                <Button type="button" variant="secondary" onClick={() => onOpenChange(false)}>
                  Annuler
                </Button>
                <Button type="submit" disabled={isPending}>
                  {isPending ? "Enregistrement..." : isEditing ? "Enregistrer" : "Créer l'horaire"}
                </Button>
              </div>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <ConfirmDialog
        open={confirmingDelete}
        onOpenChange={setConfirmingDelete}
        title="Supprimer cet horaire ?"
        description="Ce créneau de planning sera définitivement supprimé. Cette action est irréversible."
        confirmLabel="Supprimer"
        isPending={deleteSchedule.isPending}
        onConfirm={handleDelete}
      />
    </>
  );
}
