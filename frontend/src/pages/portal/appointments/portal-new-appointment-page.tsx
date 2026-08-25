import { useMemo, useState } from "react";
import { ArrowLeft, CalendarCheck, LoaderCircle } from "lucide-react";
import { Link, useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Select } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/loading-state";
import { usePortalPractitioners, usePortalSites, usePortalCreneaux } from "@/hooks/portal/use-portal-booking";
import { useCreatePortalAppointment } from "@/hooks/portal/use-portal-appointments";
import { formatDateTime } from "@/lib/datetime";
import { portalErrorMessage } from "@/lib/portal-error";

function pad(n: number): string {
  return String(n).padStart(2, "0");
}

function dateKey(d: Date): string {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

export function PortalNewAppointmentPage() {
  const navigate = useNavigate();
  const sitesQuery = usePortalSites();
  const practitionersQuery = usePortalPractitioners();

  const [siteId, setSiteId] = useState<number | null>(null);
  const [practitionerId, setPractitionerId] = useState<number | null>(null);
  const [reason, setReason] = useState("");
  const [selectedSlot, setSelectedSlot] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const from = useMemo(() => dateKey(new Date()), []);
  const to = useMemo(() => {
    const d = new Date();
    d.setDate(d.getDate() + 14);
    return dateKey(d);
  }, []);

  const creneauxQuery = usePortalCreneaux(
    practitionerId ? { practitionerId, from: `${from} 00:00`, to: `${to} 23:59`, durationMinutes: 30 } : null,
  );

  const createMutation = useCreatePortalAppointment();

  function handleConfirm() {
    if (!siteId || !practitionerId || !selectedSlot) return;
    setNotice(null);
    createMutation.mutate(
      { site_id: siteId, practitioner_id: practitionerId, starts_at: selectedSlot, duration_minutes: 30, reason: reason || undefined },
      {
        onSuccess: () => navigate("/portail/rendez-vous", { replace: true }),
        onError: (error) => setNotice(portalErrorMessage(error)),
      },
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Link to="/portail/rendez-vous" className="text-text-muted hover:text-text">
          <ArrowLeft size={18} />
        </Link>
        <h2 className="font-heading text-lg font-semibold text-text">Nouveau rendez-vous</h2>
      </div>

      <Card>
        <CardContent className="space-y-5 pt-5">
          <div className="space-y-1.5">
            <label className="text-xs font-medium text-text-muted">Site</label>
            {sitesQuery.isError ? (
              <ErrorState message={portalErrorMessage(sitesQuery.error)} onRetry={() => sitesQuery.refetch()} />
            ) : (
              <Select
                value={siteId ?? ""}
                onChange={(e) => setSiteId(e.target.value ? Number(e.target.value) : null)}
                disabled={sitesQuery.isLoading}
              >
                <option value="">Choisissez un site</option>
                {(sitesQuery.data ?? []).map((site) => (
                  <option key={site.id} value={site.id}>
                    {site.name}
                  </option>
                ))}
              </Select>
            )}
          </div>

          <div className="space-y-1.5">
            <label className="text-xs font-medium text-text-muted">Praticien</label>
            {practitionersQuery.isError ? (
              <ErrorState message={portalErrorMessage(practitionersQuery.error)} onRetry={() => practitionersQuery.refetch()} />
            ) : (
              <Select
                value={practitionerId ?? ""}
                onChange={(e) => {
                  setPractitionerId(e.target.value ? Number(e.target.value) : null);
                  setSelectedSlot(null);
                }}
                disabled={practitionersQuery.isLoading}
              >
                <option value="">Choisissez un praticien</option>
                {(practitionersQuery.data ?? []).map((p) => (
                  <option key={p.id} value={p.id}>
                    Dr {p.first_name} {p.last_name}
                  </option>
                ))}
              </Select>
            )}
          </div>

          <div className="space-y-1.5">
            <label htmlFor="reason" className="text-xs font-medium text-text-muted">
              Motif (facultatif)
            </label>
            <input
              id="reason"
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              className="h-9 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
              placeholder="Ex : consultation de suivi"
            />
          </div>

          {practitionerId && (
            <div className="space-y-1.5">
              <label className="text-xs font-medium text-text-muted">Créneaux disponibles (14 prochains jours)</label>
              {creneauxQuery.isError ? (
                <ErrorState message={portalErrorMessage(creneauxQuery.error)} onRetry={() => creneauxQuery.refetch()} />
              ) : creneauxQuery.isLoading ? (
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                  {Array.from({ length: 6 }).map((_, i) => (
                    <Skeleton key={i} className="h-9" />
                  ))}
                </div>
              ) : (creneauxQuery.data ?? []).length === 0 ? (
                <EmptyState icon={CalendarCheck} title="Aucun créneau disponible" description="Essayez un autre praticien ou revenez plus tard." />
              ) : (
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                  {(creneauxQuery.data ?? []).map((slot) => (
                    <button
                      key={slot}
                      type="button"
                      onClick={() => setSelectedSlot(slot)}
                      className={`rounded-md border px-3 py-2 text-xs font-medium transition-colors ${
                        selectedSlot === slot
                          ? "border-accent bg-accent/10 text-accent-light"
                          : "border-border bg-surface text-text-muted hover:bg-surface-hover hover:text-text"
                      }`}
                    >
                      {formatDateTime(slot)}
                    </button>
                  ))}
                </div>
              )}
            </div>
          )}

          {notice && (
            <p className="rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-xs text-warning">{notice}</p>
          )}

          <Button
            className="w-full"
            disabled={!siteId || !practitionerId || !selectedSlot || createMutation.isPending}
            onClick={handleConfirm}
          >
            {createMutation.isPending ? <LoaderCircle size={16} className="animate-spin" /> : <CalendarCheck size={16} />}
            Confirmer le rendez-vous
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
