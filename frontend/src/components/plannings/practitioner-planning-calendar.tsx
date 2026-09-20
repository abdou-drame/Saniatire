import { useMemo } from "react";
import { Badge } from "@/components/ui/badge";
import { TableSkeleton } from "@/components/ui/loading-state";
import { formatDate } from "@/lib/datetime";
import { cn } from "@/lib/utils";
import { LEAVE_STATUT_LABEL, LEAVE_TYPE_LABEL } from "@/pages/conges/conges-status";
import { WORK_SCHEDULE_TYPE_BADGE, WORK_SCHEDULE_TYPE_LABEL } from "@/pages/plannings/plannings-status";
import type { PractitionerPlanning, PractitionerPlanningHoraire } from "@/types/api";

// Mêmes helpers de calcul de date "à la main" (pas de librairie de calendrier
// installée) que appointment-calendar.tsx — semaine calée sur lundi.
function pad(n: number): string {
  return String(n).padStart(2, "0");
}

export function toDateKey(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export function parseDateKey(key: string): Date {
  const [y, m, d] = key.split("-").map(Number);
  return new Date(y, m - 1, d);
}

export function startOfWeek(date: Date): Date {
  const d = new Date(date);
  const day = (d.getDay() + 6) % 7; // Monday = 0
  d.setDate(d.getDate() - day);
  d.setHours(0, 0, 0, 0);
  return d;
}

export function addDays(date: Date, days: number): Date {
  const d = new Date(date);
  d.setDate(d.getDate() + days);
  return d;
}

export function startOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth(), 1);
}

export function endOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth() + 1, 0);
}

/** Découpe [from, to] en semaines complètes (lundi→dimanche) pour un rendu grid-cols-7 uniforme,
 * que la période demandée couvre une semaine ou un mois. */
function buildWeeks(from: string, to: string): Date[][] {
  const gridStart = startOfWeek(parseDateKey(from));
  const lastWeekStart = startOfWeek(parseDateKey(to));
  const gridEnd = addDays(lastWeekStart, 6);
  const totalDays = Math.round((gridEnd.getTime() - gridStart.getTime()) / 86_400_000) + 1;
  const weekCount = Math.max(1, Math.ceil(totalDays / 7));
  return Array.from({ length: weekCount }, (_, w) => Array.from({ length: 7 }, (_, d) => addDays(gridStart, w * 7 + d)));
}

const WEEKDAY_HEADERS = ["Lun", "Mar", "Mer", "Jeu", "Ven", "Sam", "Dim"];

export interface PractitionerPlanningCalendarProps {
  planning: PractitionerPlanning | undefined;
  isLoading: boolean;
  /** Bornes de la période demandée, au format YYYY-MM-DD (mêmes valeurs que celles passées à usePractitionerPlanning). */
  from: string;
  to: string;
  /** Fourni uniquement dans la section "Gestion des plannings" pour ouvrir l'édition d'un horaire ; absent en lecture seule ("Mon planning"). */
  onScheduleClick?: (horaire: PractitionerPlanningHoraire) => void;
}

/**
 * Rendu purement présentationnel des tableaux `horaires` / `jours_conges` /
 * `conges` renvoyés par `GET /employees/{id}/planning`. Ce composant ne
 * calcule, n'infère et n'affiche jamais de jugement de disponibilité — seul
 * `PractitionerPresenceService::isPresent()` côté backend fait autorité sur
 * la disponibilité d'un praticien. Il se contente de placer les lignes
 * renvoyées par le backend sur une grille de jours.
 */
export function PractitionerPlanningCalendar({ planning, isLoading, from, to, onScheduleClick }: PractitionerPlanningCalendarProps) {
  const weeks = useMemo(() => buildWeeks(from, to), [from, to]);
  const rangeStart = useMemo(() => parseDateKey(from), [from]);
  const rangeEnd = useMemo(() => parseDateKey(to), [to]);

  const horairesByDay = useMemo(() => {
    const map = new Map<string, PractitionerPlanningHoraire[]>();
    for (const horaire of planning?.horaires ?? []) {
      const list = map.get(horaire.date) ?? [];
      list.push(horaire);
      map.set(horaire.date, list);
    }
    return map;
  }, [planning]);

  const joursConges = useMemo(() => new Set(planning?.jours_conges ?? []), [planning]);
  const conges = planning?.conges ?? [];

  if (isLoading) {
    return <TableSkeleton rows={3} columns={7} />;
  }

  return (
    <div className="space-y-3">
      <div className="grid grid-cols-7 gap-1.5">
        {WEEKDAY_HEADERS.map((label) => (
          <div key={label} className="px-1 pb-1 text-center text-[11px] font-medium uppercase tracking-wide text-text-subtle">
            {label}
          </div>
        ))}
      </div>

      <div className="space-y-1.5">
        {weeks.map((week, weekIndex) => (
          <div key={weekIndex} className="grid grid-cols-7 gap-1.5">
            {week.map((day) => {
              const key = toDateKey(day);
              const inRange = day >= rangeStart && day <= rangeEnd;
              const dayHoraires = horairesByDay.get(key) ?? [];
              const isConge = joursConges.has(key);

              return (
                <div
                  key={key}
                  className={cn(
                    "flex min-h-[104px] flex-col gap-1 rounded-md border border-border bg-surface p-2",
                    !inRange && "opacity-40",
                    isConge && "border-dashed border-border-strong",
                  )}
                  style={
                    isConge
                      ? {
                          backgroundImage:
                            "repeating-linear-gradient(45deg, var(--color-surface-hover), var(--color-surface-hover) 6px, var(--color-surface) 6px, var(--color-surface) 12px)",
                        }
                      : undefined
                  }
                >
                  <div className="flex items-center justify-between gap-1">
                    <span className="font-tabular text-xs text-text-muted">{day.getDate()}</span>
                    {isConge && (
                      <Badge status="neutral" dot={false} className="text-[10px]">
                        En congé
                      </Badge>
                    )}
                  </div>

                  {dayHoraires.length > 0 && (
                    <div className="flex flex-1 flex-col gap-1">
                      {dayHoraires.map((horaire) => (
                        <ScheduleChip key={horaire.work_schedule_id} horaire={horaire} onClick={onScheduleClick} />
                      ))}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        ))}
      </div>

      {conges.length > 0 && (
        <div className="space-y-1.5 rounded-md border border-dashed border-border p-3">
          <p className="text-[11px] font-medium uppercase tracking-wide text-text-subtle">
            Congés sur la période (voir jours "En congé" ci-dessus)
          </p>
          <div className="flex flex-wrap gap-2">
            {conges.map((conge) => (
              <span
                key={conge.id}
                className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-2 py-1 text-xs text-text-muted"
              >
                {LEAVE_TYPE_LABEL[conge.type]} · {formatDate(conge.date_debut)} → {formatDate(conge.date_fin)} ·{" "}
                {LEAVE_STATUT_LABEL[conge.statut]}
              </span>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

function ScheduleChip({
  horaire,
  onClick,
}: {
  horaire: PractitionerPlanningHoraire;
  onClick?: (horaire: PractitionerPlanningHoraire) => void;
}) {
  const badge = (
    <Badge status={WORK_SCHEDULE_TYPE_BADGE[horaire.type]} className="w-full justify-start text-left text-[10px]">
      {WORK_SCHEDULE_TYPE_LABEL[horaire.type]} · {horaire.heure_debut.slice(0, 5)}–{horaire.heure_fin.slice(0, 5)}
    </Badge>
  );

  if (!onClick) {
    return <div>{badge}</div>;
  }

  return (
    <button type="button" onClick={() => onClick(horaire)} className="text-left" aria-label="Modifier cet horaire">
      {badge}
    </button>
  );
}
