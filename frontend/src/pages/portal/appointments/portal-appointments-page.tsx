import { CalendarDays, CalendarPlus, X } from "lucide-react";
import { useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { Badge, type BadgeProps } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { TableSkeleton } from "@/components/ui/loading-state";
import { usePortalPractitioners, usePortalSites } from "@/hooks/portal/use-portal-booking";
import { useCancelPortalAppointment, usePortalAppointments } from "@/hooks/portal/use-portal-appointments";
import { formatDateTime } from "@/lib/datetime";
import { portalErrorMessage } from "@/lib/portal-error";
import type { Appointment, AppointmentStatus } from "@/types/api";

const STATUS_META: Record<AppointmentStatus, { label: string; status: BadgeProps["status"] }> = {
  planifie: { label: "Planifié", status: "neutral" },
  confirme: { label: "Confirmé", status: "accent" },
  en_cours: { label: "En cours", status: "accent2" },
  termine: { label: "Terminé", status: "success" },
  annule: { label: "Annulé", status: "danger" },
  absent: { label: "Absence", status: "warning" },
};

function AppointmentRow({
  appointment,
  practitionerName,
  siteName,
  onCancel,
}: {
  appointment: Appointment;
  practitionerName: string;
  siteName: string;
  onCancel?: (appointment: Appointment) => void;
}) {
  const meta = STATUS_META[appointment.status];
  const isCancellable = appointment.status !== "annule" && appointment.status !== "termine";
  return (
    <div className="flex items-center justify-between gap-4 rounded-md border border-border bg-surface px-4 py-3">
      <div className="min-w-0">
        <p className="text-sm font-medium text-text">{formatDateTime(appointment.starts_at)}</p>
        <p className="truncate text-xs text-text-muted">
          {practitionerName} — {siteName}
        </p>
        {appointment.reason && <p className="truncate text-xs text-text-subtle">{appointment.reason}</p>}
      </div>
      <div className="flex shrink-0 items-center gap-2">
        <Badge status={meta.status}>{meta.label}</Badge>
        {onCancel && isCancellable && (
          <button
            type="button"
            onClick={() => onCancel(appointment)}
            className="rounded-md p-1.5 text-text-muted hover:bg-danger/10 hover:text-danger"
            aria-label="Annuler ce rendez-vous"
          >
            <X size={14} />
          </button>
        )}
      </div>
    </div>
  );
}

export function PortalAppointmentsPage() {
  const appointmentsQuery = usePortalAppointments();
  const practitionersQuery = usePortalPractitioners();
  const sitesQuery = usePortalSites();
  const cancelAppointment = useCancelPortalAppointment();
  const [cancelling, setCancelling] = useState<Appointment | null>(null);
  const [cancelError, setCancelError] = useState<string | null>(null);

  const practitionerNames = useMemo(() => {
    const map = new Map<number, string>();
    for (const p of practitionersQuery.data ?? []) map.set(p.id, `Dr ${p.first_name} ${p.last_name}`);
    return map;
  }, [practitionersQuery.data]);

  const siteNames = useMemo(() => {
    const map = new Map<number, string>();
    for (const s of sitesQuery.data ?? []) map.set(s.id, s.name);
    return map;
  }, [sitesQuery.data]);

  const { upcoming, past } = useMemo(() => {
    const now = Date.now();
    const all = appointmentsQuery.data ?? [];
    return {
      upcoming: all.filter((a) => new Date(a.starts_at).getTime() >= now).sort((a, b) => a.starts_at.localeCompare(b.starts_at)),
      past: all.filter((a) => new Date(a.starts_at).getTime() < now).sort((a, b) => b.starts_at.localeCompare(a.starts_at)),
    };
  }, [appointmentsQuery.data]);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-3">
        <h2 className="font-heading text-lg font-semibold text-text">Mes rendez-vous</h2>
        <Link
          to="/portail/rendez-vous/nouveau"
          className="flex items-center gap-1.5 rounded-md bg-accent px-3 py-2 text-sm font-medium text-white hover:bg-accent-dark"
        >
          <CalendarPlus size={15} />
          Nouveau rendez-vous
        </Link>
      </div>

      {appointmentsQuery.isError ? (
        <ErrorState message={portalErrorMessage(appointmentsQuery.error)} onRetry={() => appointmentsQuery.refetch()} />
      ) : appointmentsQuery.isLoading ? (
        <TableSkeleton rows={4} columns={2} />
      ) : (
        <>
          <Card>
            <CardContent className="space-y-3 pt-5">
              <h3 className="text-sm font-medium text-text">À venir</h3>
              {upcoming.length === 0 ? (
                <EmptyState icon={CalendarDays} title="Aucun rendez-vous à venir" description="Prenez un nouveau rendez-vous quand vous le souhaitez." />
              ) : (
                <div className="space-y-2">
                  {upcoming.map((a) => (
                    <AppointmentRow
                      key={a.id}
                      appointment={a}
                      practitionerName={practitionerNames.get(a.practitioner_id) ?? "Praticien"}
                      siteName={siteNames.get(a.site_id) ?? "Site"}
                      onCancel={(appointment) => {
                        setCancelError(null);
                        setCancelling(appointment);
                      }}
                    />
                  ))}
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardContent className="space-y-3 pt-5">
              <h3 className="text-sm font-medium text-text">Passés</h3>
              {past.length === 0 ? (
                <EmptyState icon={CalendarDays} title="Aucun rendez-vous passé" />
              ) : (
                <div className="space-y-2">
                  {past.map((a) => (
                    <AppointmentRow
                      key={a.id}
                      appointment={a}
                      practitionerName={practitionerNames.get(a.practitioner_id) ?? "Praticien"}
                      siteName={siteNames.get(a.site_id) ?? "Site"}
                    />
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </>
      )}

      <ConfirmDialog
        open={Boolean(cancelling)}
        onOpenChange={(open) => {
          if (!open) {
            setCancelling(null);
            setCancelError(null);
          }
        }}
        title="Annuler ce rendez-vous ?"
        description={
          cancelling
            ? `Votre rendez-vous du ${formatDateTime(cancelling.starts_at)} sera annulé. Cette action est irréversible.${
                cancelError ? ` ${cancelError}` : ""
              }`
            : ""
        }
        confirmLabel="Annuler le rendez-vous"
        isPending={cancelAppointment.isPending}
        onConfirm={() => {
          if (!cancelling) return;
          cancelAppointment.mutate(cancelling.id, {
            onSuccess: () => {
              setCancelling(null);
              setCancelError(null);
            },
            onError: (error) => setCancelError(portalErrorMessage(error)),
          });
        }}
      />
    </div>
  );
}
