import { CalendarClock, PhoneCall, Siren } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/loading-state";
import { Select } from "@/components/ui/select";
import { useAuth } from "@/hooks/use-auth";
import { useOnCallBetween, useOnCallNow } from "@/hooks/use-on-call";
import { useSites } from "@/hooks/use-sites";
import { useUsersDirectory } from "@/hooks/use-users-directory";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import { ON_CALL_TYPE_BADGE, ON_CALL_TYPE_LABEL } from "@/pages/gardes-astreintes/gardes-astreintes-status";
import type { OnCallEntry, Site, StaffUser } from "@/types/api";

/** "HH:MM:SS" (SQL time column) -> "HH:MM". Not an ISO datetime, so the
 * datetime.ts formatters (which parse via `new Date(...)`) don't apply. */
function formatHms(value: string): string {
  return value.slice(0, 5);
}

function toDateInputValue(date: Date): string {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

/** Monday..Sunday of the current week, as the default range for the history section. */
function currentWeekRange(): { from: string; to: string } {
  const now = new Date();
  const day = now.getDay(); // 0 = dimanche .. 6 = samedi
  const diffToMonday = day === 0 ? -6 : 1 - day;
  const monday = new Date(now);
  monday.setDate(now.getDate() + diffToMonday);
  const sunday = new Date(monday);
  sunday.setDate(monday.getDate() + 6);
  return { from: toDateInputValue(monday), to: toDateInputValue(sunday) };
}

function siteName(sites: Site[] | undefined, siteId: number): string {
  return sites?.find((s) => s.id === siteId)?.name ?? `Site #${siteId}`;
}

function userLabel(byId: Map<number, StaffUser>, userId: number): string {
  const user = byId.get(userId);
  return user ? `${user.first_name} ${user.last_name}` : `Utilisateur #${userId}`;
}

/**
 * Écran "Gardes / astreintes" — usage d'urgence assumé : la section A doit
 * rester lisible en un coup d'œil (grandes cartes, jamais un tableau dense)
 * et reste accessible à tout utilisateur authentifié, sans gate de
 * permission, à l'image de la route backend GET /on-call/now qui est
 * volontairement ouverte. Seule la section historique (GET /on-call) est
 * réservée à rh.view, comme le fait le backend lui-même.
 */
export function GardesAstreintesPage() {
  const { hasPermission } = useAuth();

  const [siteFilter, setSiteFilter] = useState<number | "">("");

  // Cet écran est ouvert à tout utilisateur authentifié, alors que GET /sites
  // exige `sites.view` : on n'appelle donc l'endroit qu'en présence de la
  // permission, et le filtre par site n'est proposé que dans ce cas.
  const canViewSites = hasPermission("sites.view");
  const sitesQuery = useSites(canViewSites);
  const nowQuery = useOnCallNow({ siteId: siteFilter || undefined });
  const canViewUsers = hasPermission("users.view");
  const usersQuery = useUsersDirectory(canViewUsers);
  const canViewHistory = hasPermission("rh.view");

  return (
    <div className="space-y-6">
      <div>
        <h1 className="font-heading text-xl font-semibold text-text">Gardes / astreintes</h1>
        <p className="mt-1 text-sm text-text-muted">
          Qui est de garde ou d'astreinte en ce moment, et la planification associée.
        </p>
      </div>

      <Card>
        <CardHeader>
          <div className="flex items-center gap-2">
            <Siren size={18} className="text-danger" />
            <CardTitle>De garde maintenant</CardTitle>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          {canViewSites && (
            <div className="w-56">
              <Label>Site</Label>
              <Select
                value={siteFilter}
                onChange={(e) => setSiteFilter(e.target.value ? Number(e.target.value) : "")}
                disabled={sitesQuery.isLoading}
              >
                <option value="">Tous les sites</option>
                {(sitesQuery.data ?? []).map((site) => (
                  <option key={site.id} value={site.id}>
                    {site.name}
                  </option>
                ))}
              </Select>
            </div>
          )}

          {nowQuery.isError ? (
            <ErrorState message={apiErrorMessage(nowQuery.error)} onRetry={() => nowQuery.refetch()} />
          ) : nowQuery.isLoading ? (
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {[0, 1, 2].map((i) => (
                <div key={i} className="rounded-lg border border-border bg-surface p-6">
                  <Skeleton className="h-4 w-24" />
                  <Skeleton className="mt-4 h-7 w-40" />
                  <Skeleton className="mt-4 h-4 w-32" />
                </div>
              ))}
            </div>
          ) : (nowQuery.data ?? []).length === 0 ? (
            <EmptyState
              icon={PhoneCall}
              title="Personne n'est actuellement de garde ou d'astreinte"
              description={
                siteFilter
                  ? "Aucune garde ni astreinte en cours sur ce site."
                  : "Aucune garde ni astreinte en cours, tous sites confondus."
              }
            />
          ) : (
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {(nowQuery.data ?? []).map((entry) => (
                <div
                  key={entry.id}
                  className="flex flex-col gap-3 rounded-lg border border-border-strong bg-surface p-6 shadow-[var(--shadow-card)]"
                >
                  <Badge status={ON_CALL_TYPE_BADGE[entry.type as "garde" | "astreinte"]} className="w-fit text-sm">
                    {ON_CALL_TYPE_LABEL[entry.type as "garde" | "astreinte"]}
                  </Badge>
                  <p className="font-heading text-xl font-semibold leading-tight text-text">
                    {userLabel(usersQuery.byId, entry.user_id)}
                  </p>
                  <div className="space-y-1 text-sm text-text-muted">
                    <p>
                      {formatHms(entry.heure_debut)} – {formatHms(entry.heure_fin)}
                    </p>
                    <p>{siteName(sitesQuery.data, entry.site_id)}</p>
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      {canViewHistory && (
        <OnCallHistorySection siteFilter={siteFilter} sites={sitesQuery.data} usersByIdEnabled={canViewUsers} />
      )}
    </div>
  );
}

/**
 * No route-level permission gate on purpose — Section A (the emergency
 * "who's on call now" view) must stay reachable by any authenticated user,
 * matching the backend's own ungated GET /on-call/now. Section B gates
 * itself internally on rh.view. Exported for whichever route table wires
 * this page in (see the *Route pattern used by sibling pages in App.tsx).
 */
export function GardesAstreintesRoute() {
  return <GardesAstreintesPage />;
}

function OnCallHistorySection({
  siteFilter,
  sites,
  usersByIdEnabled,
}: {
  siteFilter: number | "";
  sites: Site[] | undefined;
  usersByIdEnabled: boolean;
}) {
  const defaultRange = currentWeekRange();
  const [from, setFrom] = useState(defaultRange.from);
  const [to, setTo] = useState(defaultRange.to);

  const usersQuery = useUsersDirectory(usersByIdEnabled);
  const historyQuery = useOnCallBetween({ from, to, siteId: siteFilter || undefined });

  const entries = historyQuery.data ?? [];
  const byDate = new Map<string, OnCallEntry[]>();
  for (const entry of entries) {
    const list = byDate.get(entry.date) ?? [];
    list.push(entry);
    byDate.set(entry.date, list);
  }
  const dates = [...byDate.keys()].sort();

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center gap-2">
          <CalendarClock size={18} className="text-text-muted" />
          <CardTitle>Historique / planification</CardTitle>
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-end gap-3">
          <div className="w-40">
            <Label>Du</Label>
            <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} max={to} />
          </div>
          <div className="w-40">
            <Label>Au</Label>
            <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} min={from} />
          </div>
        </div>

        {historyQuery.isError ? (
          <ErrorState message={apiErrorMessage(historyQuery.error)} onRetry={() => historyQuery.refetch()} />
        ) : historyQuery.isLoading ? (
          <div className="space-y-2">
            {[0, 1, 2].map((i) => (
              <Skeleton key={i} className="h-14 w-full" />
            ))}
          </div>
        ) : dates.length === 0 ? (
          <EmptyState
            icon={CalendarClock}
            title="Aucune garde ni astreinte sur cette période"
            description="Ajustez la période ou le site pour voir d'autres résultats."
          />
        ) : (
          <div className="space-y-4">
            {dates.map((date) => (
              <div key={date}>
                <p className="mb-2 text-xs font-medium uppercase tracking-wide text-text-subtle">
                  {formatDate(date)}
                </p>
                <div className="divide-y divide-border rounded-md border border-border">
                  {byDate.get(date)!.map((entry, idx) => (
                    <div
                      key={`${entry.user_id}-${entry.site_id}-${entry.heure_debut}-${idx}`}
                      className="flex flex-wrap items-center justify-between gap-3 px-3 py-2.5 text-sm"
                    >
                      <div>
                        <p className="text-text">{userLabel(usersQuery.byId, entry.user_id)}</p>
                        <p className="text-xs text-text-muted">
                          {siteName(sites, entry.site_id)} · {formatHms(entry.heure_debut)} – {formatHms(entry.heure_fin)}
                        </p>
                      </div>
                      <Badge status={ON_CALL_TYPE_BADGE[entry.type]}>{ON_CALL_TYPE_LABEL[entry.type]}</Badge>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
