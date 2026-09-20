import { ChevronLeft, ChevronRight, Plus, Users } from "lucide-react";
import { useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import {
  addDays,
  endOfMonth,
  PractitionerPlanningCalendar,
  startOfMonth,
  startOfWeek,
  toDateKey,
} from "@/components/plannings/practitioner-planning-calendar";
import { WorkScheduleFormDialog } from "@/components/plannings/work-schedule-form-dialog";
import { useAuth } from "@/hooks/use-auth";
import { usePractitionerPlanning } from "@/hooks/use-work-schedules";
import { useUsersDirectory } from "@/hooks/use-users-directory";
import { apiErrorMessage } from "@/lib/api-error";
import { cn } from "@/lib/utils";
import type { PractitionerPlanningHoraire, WorkSchedule } from "@/types/api";

type ViewMode = "semaine" | "mois";

function useDateRange(defaultMode: ViewMode = "semaine") {
  const [viewMode, setViewMode] = useState<ViewMode>(defaultMode);
  const [refDate, setRefDate] = useState(() => new Date());

  const range = useMemo(() => {
    if (viewMode === "semaine") {
      const start = startOfWeek(refDate);
      return { from: start, to: addDays(start, 6) };
    }
    return { from: startOfMonth(refDate), to: endOfMonth(refDate) };
  }, [viewMode, refDate]);

  function navigate(direction: -1 | 1) {
    if (viewMode === "semaine") setRefDate((d) => addDays(d, direction * 7));
    else setRefDate((d) => new Date(d.getFullYear(), d.getMonth() + direction, 1));
  }

  const rangeLabel =
    viewMode === "semaine"
      ? `${range.from.toLocaleDateString("fr-FR", { day: "2-digit", month: "short" })} — ${range.to.toLocaleDateString("fr-FR", { day: "2-digit", month: "short", year: "numeric" })}`
      : refDate.toLocaleDateString("fr-FR", { month: "long", year: "numeric" });

  return { viewMode, setViewMode, refDate, setRefDate, range, navigate, rangeLabel };
}

function PlanningNav({
  viewMode,
  setViewMode,
  navigate,
  goToday,
  rangeLabel,
}: {
  viewMode: ViewMode;
  setViewMode: (mode: ViewMode) => void;
  navigate: (direction: -1 | 1) => void;
  goToday: () => void;
  rangeLabel: string;
}) {
  return (
    <div className="flex flex-wrap items-center gap-2">
      <div className="flex rounded-md border border-border p-0.5">
        {(["semaine", "mois"] as ViewMode[]).map((mode) => (
          <button
            key={mode}
            type="button"
            onClick={() => setViewMode(mode)}
            className={cn(
              "rounded px-3 py-1.5 text-xs font-medium capitalize transition-colors",
              viewMode === mode ? "bg-accent text-white" : "text-text-muted hover:bg-surface-hover",
            )}
          >
            {mode}
          </button>
        ))}
      </div>
      <button
        type="button"
        onClick={() => navigate(-1)}
        className="rounded-md p-1.5 text-text-muted hover:bg-surface-hover hover:text-text"
        aria-label="Précédent"
      >
        <ChevronLeft size={16} />
      </button>
      <button
        type="button"
        onClick={() => navigate(1)}
        className="rounded-md p-1.5 text-text-muted hover:bg-surface-hover hover:text-text"
        aria-label="Suivant"
      >
        <ChevronRight size={16} />
      </button>
      <Button variant="ghost" size="sm" onClick={goToday}>
        Aujourd'hui
      </Button>
      <span className="text-sm font-medium capitalize text-text">{rangeLabel}</span>
    </div>
  );
}

/**
 * Écran Plannings. Affiche uniquement les données de planning
 * (WorkSchedule / PractitionerPlanning) renvoyées par le backend — il ne
 * calcule ni n'infère jamais de disponibilité : seul
 * `PractitionerPresenceService::isPresent()` côté backend fait autorité
 * pour savoir si un praticien est disponible pour un rendez-vous.
 */
export function PlanningsPage() {
  const { user, hasPermission } = useAuth();
  const canManage = hasPermission("rh.create") || hasPermission("rh.update");

  return (
    <div className="space-y-6">
      <div>
        <h1 className="font-heading text-xl font-semibold text-text">Plannings</h1>
        <p className="mt-1 text-sm text-text-muted">Horaires, gardes et astreintes du personnel.</p>
      </div>

      <MyPlanningSection userId={user?.id} />

      {canManage && <ManagePlanningsSection />}
    </div>
  );
}

function MyPlanningSection({ userId }: { userId: number | undefined }) {
  const { viewMode, setViewMode, setRefDate, range, navigate, rangeLabel } = useDateRange();
  const from = toDateKey(range.from);
  const to = toDateKey(range.to);
  const planningQuery = usePractitionerPlanning(userId, from, to);

  return (
    <Card>
      <CardHeader>
        <CardTitle>Mon planning</CardTitle>
        <PlanningNav
          viewMode={viewMode}
          setViewMode={setViewMode}
          navigate={navigate}
          goToday={() => setRefDate(new Date())}
          rangeLabel={rangeLabel}
        />
      </CardHeader>
      <CardContent>
        {planningQuery.isError ? (
          <ErrorState message={apiErrorMessage(planningQuery.error)} onRetry={() => planningQuery.refetch()} />
        ) : (
          <PractitionerPlanningCalendar planning={planningQuery.data} isLoading={planningQuery.isLoading} from={from} to={to} />
        )}
      </CardContent>
    </Card>
  );
}

function ManagePlanningsSection() {
  const { hasPermission } = useAuth();
  const canCreate = hasPermission("rh.create");
  const canUpdate = hasPermission("rh.update");
  const canDelete = hasPermission("rh.delete");

  const usersQuery = useUsersDirectory(hasPermission("users.view"));
  const [selectedUserId, setSelectedUserId] = useState<string>("");
  const [formOpen, setFormOpen] = useState(false);
  const [editingSchedule, setEditingSchedule] = useState<WorkSchedule | null>(null);

  const { viewMode, setViewMode, setRefDate, range, navigate, rangeLabel } = useDateRange();
  const from = toDateKey(range.from);
  const to = toDateKey(range.to);

  const userId = selectedUserId ? Number(selectedUserId) : undefined;
  const planningQuery = usePractitionerPlanning(userId, from, to);

  function openCreate() {
    setEditingSchedule(null);
    setFormOpen(true);
  }

  /** L'horaire renvoyé par le planning n'est qu'un extrait (PractitionerPlanningHoraire) —
   * on reconstitue un WorkSchedule complet à partir de ses champs communs pour alimenter le
   * formulaire d'édition, qui n'a besoin que de ces champs (les autres, created_at/structure_id,
   * ne sont pas affichés). */
  function openEdit(horaire: PractitionerPlanningHoraire) {
    if (!canUpdate || !userId) return;
    setEditingSchedule({
      id: horaire.work_schedule_id,
      structure_id: 0,
      user_id: userId,
      site_id: horaire.site_id,
      jour_semaine: null,
      date: horaire.date,
      heure_debut: horaire.heure_debut,
      heure_fin: horaire.heure_fin,
      type: horaire.type,
      created_at: "",
      updated_at: "",
    });
    setFormOpen(true);
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Gestion des plannings</CardTitle>
        {canCreate && (
          <Button size="sm" onClick={openCreate} disabled={!userId}>
            <Plus size={14} />
            Nouvel horaire
          </Button>
        )}
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="w-64">
          <Label>Praticien</Label>
          <Select
            value={selectedUserId}
            onChange={(e) => setSelectedUserId(e.target.value)}
            disabled={usersQuery.isLoading}
          >
            <option value="">Sélectionner un praticien...</option>
            {(usersQuery.data ?? []).map((u) => (
              <option key={u.id} value={u.id}>
                {u.first_name} {u.last_name}
              </option>
            ))}
          </Select>
        </div>

        {usersQuery.isError && (
          <ErrorState message={apiErrorMessage(usersQuery.error)} onRetry={() => usersQuery.refetch()} />
        )}

        {!userId ? (
          <EmptyState
            icon={Users}
            title="Aucun praticien sélectionné"
            description="Sélectionnez un praticien pour consulter et gérer son planning."
          />
        ) : (
          <div className="space-y-3">
            <PlanningNav
              viewMode={viewMode}
              setViewMode={setViewMode}
              navigate={navigate}
              goToday={() => setRefDate(new Date())}
              rangeLabel={rangeLabel}
            />
            {planningQuery.isError ? (
              <ErrorState message={apiErrorMessage(planningQuery.error)} onRetry={() => planningQuery.refetch()} />
            ) : (
              <PractitionerPlanningCalendar
                planning={planningQuery.data}
                isLoading={planningQuery.isLoading}
                from={from}
                to={to}
                onScheduleClick={canUpdate ? openEdit : undefined}
              />
            )}
          </div>
        )}
      </CardContent>

      {userId && (
        <WorkScheduleFormDialog
          open={formOpen}
          onOpenChange={setFormOpen}
          userId={userId}
          schedule={editingSchedule}
          canDelete={canDelete}
        />
      )}
    </Card>
  );
}

/** Aucun gate de permission au niveau de la route : tout utilisateur authentifié
 * peut consulter son propre planning (section "Mon planning" est toujours affichée). */
export function PlanningsRoute() {
  return <PlanningsPage />;
}
