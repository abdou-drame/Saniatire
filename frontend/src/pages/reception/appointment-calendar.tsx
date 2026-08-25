import { CalendarDays, ChevronLeft, ChevronRight, Pencil, Plus, X } from "lucide-react";
import { useMemo, useState, type ReactNode } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { TableSkeleton } from "@/components/ui/loading-state";
import { Select } from "@/components/ui/select";
import { useAppointments, useCancelAppointment } from "@/hooks/use-appointments";
import { usePatientsDirectory } from "@/hooks/use-patients-directory";
import { usePractitioners } from "@/hooks/use-practitioners";
import { apiErrorMessage } from "@/lib/api-error";
import { cn } from "@/lib/utils";
import type { Appointment, AppointmentStatus } from "@/types/api";
import { AppointmentFormDialog } from "@/pages/reception/appointment-form-dialog";

type ViewMode = "jour" | "semaine" | "mois";

const STATUS_META: Record<AppointmentStatus, { label: string; status: "neutral" | "accent" | "accent2" | "success" | "danger" | "warning" }> = {
  planifie: { label: "Planifié", status: "neutral" },
  confirme: { label: "Confirmé", status: "accent" },
  en_cours: { label: "En cours", status: "accent2" },
  termine: { label: "Terminé", status: "success" },
  annule: { label: "Annulé", status: "danger" },
  absent: { label: "Absent", status: "warning" },
};

function pad(n: number) {
  return String(n).padStart(2, "0");
}

function toDateKey(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function startOfWeek(date: Date): Date {
  const d = new Date(date);
  const day = (d.getDay() + 6) % 7; // Monday = 0
  d.setDate(d.getDate() - day);
  d.setHours(0, 0, 0, 0);
  return d;
}

function addDays(date: Date, days: number): Date {
  const d = new Date(date);
  d.setDate(d.getDate() + days);
  return d;
}

function startOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth(), 1);
}

function endOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth() + 1, 0);
}

export interface AppointmentCalendarProps {
  siteId: number;
}

export function AppointmentCalendar({ siteId }: AppointmentCalendarProps) {
  const [viewMode, setViewMode] = useState<ViewMode>("jour");
  const [refDate, setRefDate] = useState(() => new Date());
  const [practitionerId, setPractitionerId] = useState<string>("");
  const [formOpen, setFormOpen] = useState(false);
  const [editingAppointment, setEditingAppointment] = useState<Appointment | null>(null);
  const [cancelling, setCancelling] = useState<Appointment | null>(null);

  const practitioners = usePractitioners();
  const directory = usePatientsDirectory();
  const cancelAppointment = useCancelAppointment();

  const range = useMemo(() => {
    if (viewMode === "jour") return { from: refDate, to: refDate };
    if (viewMode === "semaine") {
      const start = startOfWeek(refDate);
      return { from: start, to: addDays(start, 6) };
    }
    return { from: startOfMonth(refDate), to: endOfMonth(refDate) };
  }, [viewMode, refDate]);

  const appointmentsQuery = useAppointments({
    from: toDateKey(range.from),
    to: toDateKey(range.to),
    siteId,
    practitionerId: practitionerId ? Number(practitionerId) : undefined,
  });

  function navigate(direction: -1 | 1) {
    if (viewMode === "jour") setRefDate((d) => addDays(d, direction));
    else if (viewMode === "semaine") setRefDate((d) => addDays(d, direction * 7));
    else setRefDate((d) => new Date(d.getFullYear(), d.getMonth() + direction, 1));
  }

  function practitionerName(id: number): string {
    const p = practitioners.data?.find((p) => p.id === id);
    return p ? `Dr ${p.first_name} ${p.last_name}` : `Praticien #${id}`;
  }

  function patientName(id: number): string {
    const p = directory.byId.get(id);
    return p ? `${p.first_name} ${p.last_name}` : `Patient #${id}`;
  }

  function openCreate() {
    setEditingAppointment(null);
    setFormOpen(true);
  }

  function openEdit(appointment: Appointment) {
    setEditingAppointment(appointment);
    setFormOpen(true);
  }

  const rangeLabel =
    viewMode === "jour"
      ? refDate.toLocaleDateString("fr-FR", { weekday: "long", day: "2-digit", month: "long", year: "numeric" })
      : viewMode === "semaine"
        ? `${range.from.toLocaleDateString("fr-FR", { day: "2-digit", month: "short" })} — ${range.to.toLocaleDateString("fr-FR", { day: "2-digit", month: "short", year: "numeric" })}`
        : refDate.toLocaleDateString("fr-FR", { month: "long", year: "numeric" });

  function AppointmentRow({ appointment }: { appointment: Appointment }) {
    const isCancellable = appointment.status !== "annule" && appointment.status !== "termine";
    return (
      <div className="flex items-center justify-between gap-3 rounded-md border border-border bg-surface px-4 py-3">
        <div className="flex min-w-0 items-center gap-4">
          <span className="font-tabular text-sm font-medium text-text">
            {new Date(appointment.starts_at).toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" })}
          </span>
          <div className="min-w-0">
            <p className="truncate text-sm font-medium text-text">{patientName(appointment.patient_id)}</p>
            <p className="truncate text-xs text-text-subtle">
              {practitionerName(appointment.practitioner_id)}
              {appointment.reason ? ` · ${appointment.reason}` : ""}
            </p>
          </div>
        </div>
        <div className="flex shrink-0 items-center gap-2">
          <Badge status={STATUS_META[appointment.status].status}>{STATUS_META[appointment.status].label}</Badge>
          {isCancellable && (
            <>
              <button
                type="button"
                onClick={() => openEdit(appointment)}
                className="rounded-md p-1.5 text-text-muted hover:bg-surface-hover hover:text-text"
                aria-label="Reporter"
              >
                <Pencil size={14} />
              </button>
              <button
                type="button"
                onClick={() => setCancelling(appointment)}
                className="rounded-md p-1.5 text-text-muted hover:bg-danger/10 hover:text-danger"
                aria-label="Annuler"
              >
                <X size={14} />
              </button>
            </>
          )}
        </div>
      </div>
    );
  }

  const appointments = appointmentsQuery.data ?? [];
  const sortedAppointments = [...appointments].sort(
    (a, b) => new Date(a.starts_at).getTime() - new Date(b.starts_at).getTime(),
  );

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <div className="flex rounded-md border border-border p-0.5">
            {(["jour", "semaine", "mois"] as ViewMode[]).map((mode) => (
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
          <Button variant="ghost" size="sm" onClick={() => setRefDate(new Date())}>
            Aujourd'hui
          </Button>
          <span className="text-sm font-medium capitalize text-text">{rangeLabel}</span>
        </div>

        <div className="flex items-center gap-2">
          <Select
            value={practitionerId}
            onChange={(e) => setPractitionerId(e.target.value)}
            className="w-52"
          >
            <option value="">Tous les praticiens</option>
            {(practitioners.data ?? []).map((p) => (
              <option key={p.id} value={p.id}>
                Dr {p.first_name} {p.last_name}
              </option>
            ))}
          </Select>
          <Button size="sm" onClick={openCreate}>
            <Plus size={14} />
            Nouveau rendez-vous
          </Button>
        </div>
      </div>

      {appointmentsQuery.isError ? (
        <ErrorState message={apiErrorMessage(appointmentsQuery.error)} onRetry={() => appointmentsQuery.refetch()} />
      ) : appointmentsQuery.isLoading ? (
        <TableSkeleton rows={4} columns={3} />
      ) : viewMode === "mois" ? (
        <MonthGrid
          refDate={refDate}
          appointments={sortedAppointments}
          onSelectDay={(date) => {
            setRefDate(date);
            setViewMode("jour");
          }}
        />
      ) : sortedAppointments.length === 0 ? (
        <EmptyState
          icon={CalendarDays}
          title="Aucun rendez-vous"
          description="Aucun rendez-vous programmé sur cette période pour ce site."
          actionLabel="Nouveau rendez-vous"
          onAction={openCreate}
        />
      ) : viewMode === "jour" ? (
        <div className="space-y-2">
          {sortedAppointments.map((appointment) => (
            <AppointmentRow key={appointment.id} appointment={appointment} />
          ))}
        </div>
      ) : (
        <WeekColumns
          weekStart={range.from}
          appointments={sortedAppointments}
          renderRow={(appointment) => <AppointmentRow key={appointment.id} appointment={appointment} />}
        />
      )}

      <AppointmentFormDialog
        open={formOpen}
        onOpenChange={setFormOpen}
        siteId={siteId}
        appointment={editingAppointment}
        defaultStartsAt={viewMode === "jour" ? refDate.toISOString() : undefined}
      />

      <ConfirmDialog
        open={Boolean(cancelling)}
        onOpenChange={(open) => !open && setCancelling(null)}
        title="Annuler ce rendez-vous ?"
        description={
          cancelling
            ? `Le rendez-vous de ${patientName(cancelling.patient_id)} sera marqué comme annulé. Cette action est irréversible.`
            : ""
        }
        confirmLabel="Annuler le rendez-vous"
        isPending={cancelAppointment.isPending}
        onConfirm={() => {
          if (!cancelling) return;
          cancelAppointment.mutate(cancelling.id, { onSuccess: () => setCancelling(null) });
        }}
      />
    </div>
  );
}

function MonthGrid({
  refDate,
  appointments,
  onSelectDay,
}: {
  refDate: Date;
  appointments: Appointment[];
  onSelectDay: (date: Date) => void;
}) {
  const monthStart = startOfMonth(refDate);
  const gridStart = startOfWeek(monthStart);
  const days = Array.from({ length: 42 }, (_, i) => addDays(gridStart, i));

  const countsByDay = useMemo(() => {
    const map = new Map<string, number>();
    for (const appointment of appointments) {
      const key = toDateKey(new Date(appointment.starts_at));
      map.set(key, (map.get(key) ?? 0) + 1);
    }
    return map;
  }, [appointments]);

  return (
    <div className="grid grid-cols-7 gap-1.5">
      {["Lun", "Mar", "Mer", "Jeu", "Ven", "Sam", "Dim"].map((label) => (
        <div key={label} className="px-1 pb-1 text-center text-[11px] font-medium uppercase tracking-wide text-text-subtle">
          {label}
        </div>
      ))}
      {days.map((day) => {
        const key = toDateKey(day);
        const count = countsByDay.get(key) ?? 0;
        const isCurrentMonth = day.getMonth() === refDate.getMonth();
        return (
          <button
            key={key}
            type="button"
            onClick={() => onSelectDay(day)}
            className={cn(
              "flex h-20 flex-col items-start gap-1 rounded-md border border-border bg-surface p-2 text-left hover:border-border-strong",
              !isCurrentMonth && "opacity-40",
            )}
          >
            <span className="font-tabular text-xs text-text-muted">{day.getDate()}</span>
            {count > 0 && <Badge status="accent" dot={false}>{count}</Badge>}
          </button>
        );
      })}
    </div>
  );
}

function WeekColumns({
  weekStart,
  appointments,
  renderRow,
}: {
  weekStart: Date;
  appointments: Appointment[];
  renderRow: (appointment: Appointment) => ReactNode;
}) {
  const days = Array.from({ length: 7 }, (_, i) => addDays(weekStart, i));

  return (
    <div className="grid grid-cols-1 gap-3 lg:grid-cols-7">
      {days.map((day) => {
        const key = toDateKey(day);
        const dayAppointments = appointments.filter((a) => toDateKey(new Date(a.starts_at)) === key);
        return (
          <div key={key} className="space-y-2">
            <p className="text-xs font-medium capitalize text-text-subtle">
              {day.toLocaleDateString("fr-FR", { weekday: "short", day: "2-digit", month: "2-digit" })}
            </p>
            {dayAppointments.length === 0 ? (
              <p className="rounded-md border border-dashed border-border px-2 py-3 text-center text-[11px] text-text-subtle">
                —
              </p>
            ) : (
              <div className="space-y-1.5">{dayAppointments.map(renderRow)}</div>
            )}
          </div>
        );
      })}
    </div>
  );
}
