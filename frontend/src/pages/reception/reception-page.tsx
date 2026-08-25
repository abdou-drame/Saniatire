import { useEffect, useState } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ErrorState } from "@/components/ui/error-state";
import { KpiRowSkeleton } from "@/components/ui/loading-state";
import { Select } from "@/components/ui/select";
import { useAuth } from "@/hooks/use-auth";
import { useSites } from "@/hooks/use-sites";
import { apiErrorMessage } from "@/lib/api-error";
import { AppointmentCalendar } from "@/pages/reception/appointment-calendar";
import { QueuePanel } from "@/pages/reception/queue-panel";

export function ReceptionPage() {
  const { user } = useAuth();
  const sitesQuery = useSites();
  const [siteId, setSiteId] = useState<number | null>(null);

  useEffect(() => {
    if (siteId !== null) return;
    const sites = sitesQuery.data;
    if (!sites || sites.length === 0) return;
    const userSiteId = user?.sites?.[0]?.id;
    setSiteId(sites.some((s) => s.id === userSiteId) ? userSiteId! : sites[0].id);
  }, [siteId, sitesQuery.data, user]);

  if (sitesQuery.isError) {
    return <ErrorState message={apiErrorMessage(sitesQuery.error)} onRetry={() => sitesQuery.refetch()} />;
  }

  if (sitesQuery.isLoading || siteId === null) {
    return <KpiRowSkeleton count={3} />;
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="font-heading text-xl font-semibold text-text">Accueil / Secrétariat</h2>
          <p className="mt-1 text-sm text-text-muted">Rendez-vous et file d'attente du site sélectionné.</p>
        </div>
        <Select value={siteId} onChange={(e) => setSiteId(Number(e.target.value))} className="w-56">
          {(sitesQuery.data ?? []).map((site) => (
            <option key={site.id} value={site.id}>
              {site.name}
            </option>
          ))}
        </Select>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Calendrier des rendez-vous</CardTitle>
        </CardHeader>
        <CardContent>
          <AppointmentCalendar siteId={siteId} />
        </CardContent>
      </Card>

      <QueuePanel siteId={siteId} />
    </div>
  );
}
