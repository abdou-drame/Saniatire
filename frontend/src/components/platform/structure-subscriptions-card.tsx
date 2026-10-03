import { CalendarClock, LoaderCircle, Plus } from "lucide-react";
import { useState, type FormEvent } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Label } from "@/components/ui/label";
import { TableSkeleton } from "@/components/ui/loading-state";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import {
  useCreatePlatformSubscription,
  usePlatformPlans,
  usePlatformStructureSubscriptions,
  useTogglePlatformSubscription,
} from "@/hooks/use-platform-subscriptions";
import { apiErrorMessage } from "@/lib/api-error";
import { cn } from "@/lib/utils";
import type { Subscription, SubscriptionPeriodStatus, SubscriptionStateCode } from "@/types/api";

const STATE_BADGE: Record<SubscriptionStateCode, { label: string; status: "success" | "warning" | "danger" }> = {
  essai_ou_actif: { label: "Normal", status: "success" },
  en_grace: { label: "En délai de grâce", status: "warning" },
  lecture_seule: { label: "Lecture seule", status: "danger" },
};

const STATUS_LABEL: Record<SubscriptionPeriodStatus, string> = {
  essai: "Essai",
  active: "Active",
  suspendue: "Suspendue",
  resiliee: "Résiliée",
};

const dateInputClass =
  "h-9 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent";

function formatDate(value: string): string {
  return new Date(`${value}T00:00:00`).toLocaleDateString("fr-FR");
}

function formatFcfa(value: number | null): string {
  return value === null ? "sur devis" : `${value.toLocaleString("fr-FR")} FCFA`;
}

function isoDate(date: Date): string {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

function defaultPeriod(): { startsAt: string; endsAt: string } {
  const start = new Date();
  const end = new Date(start);
  end.setFullYear(end.getFullYear() + 1);
  end.setDate(end.getDate() - 1);
  return { startsAt: isoDate(start), endsAt: isoDate(end) };
}

function NewPeriodForm({ structureId, onDone }: { structureId: number; onDone: () => void }) {
  const plansQuery = usePlatformPlans();
  const createSubscription = useCreatePlatformSubscription(structureId);
  const initial = defaultPeriod();
  const [planId, setPlanId] = useState("");
  const [startsAt, setStartsAt] = useState(initial.startsAt);
  const [endsAt, setEndsAt] = useState(initial.endsAt);
  const [status, setStatus] = useState<"essai" | "active">("active");
  const [notes, setNotes] = useState("");
  const [error, setError] = useState<string | null>(null);

  const activePlans = plansQuery.data?.filter((plan) => plan.is_active) ?? [];

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    createSubscription.mutate(
      { plan_id: Number(planId), starts_at: startsAt, ends_at: endsAt, status, notes: notes || undefined },
      { onSuccess: onDone, onError: (err) => setError(apiErrorMessage(err)) },
    );
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-3 border-t border-border bg-surface-hover/30 p-4 sm:p-5">
      <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Nouvelle période d'abonnement</p>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
          <Label htmlFor="subscription-plan">Formule</Label>
          <Select id="subscription-plan" value={planId} onChange={(e) => setPlanId(e.target.value)} required>
            <option value="" disabled>
              {plansQuery.isLoading ? "Chargement…" : "Choisir une formule"}
            </option>
            {activePlans.map((plan) => (
              <option key={plan.id} value={plan.id}>
                {plan.name} · {formatFcfa(plan.monthly_price_fcfa)}/mois
              </option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="subscription-status">Type de période</Label>
          <Select
            id="subscription-status"
            value={status}
            onChange={(e) => setStatus(e.target.value as "essai" | "active")}
          >
            <option value="active">Active (payée)</option>
            <option value="essai">Essai</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="subscription-start">Début</Label>
          <input
            id="subscription-start"
            type="date"
            value={startsAt}
            onChange={(e) => setStartsAt(e.target.value)}
            className={dateInputClass}
            required
          />
        </div>
        <div>
          <Label htmlFor="subscription-end">Fin (incluse)</Label>
          <input
            id="subscription-end"
            type="date"
            value={endsAt}
            onChange={(e) => setEndsAt(e.target.value)}
            className={dateInputClass}
            required
          />
        </div>
      </div>
      <div>
        <Label htmlFor="subscription-notes">Notes (optionnel)</Label>
        <Textarea
          id="subscription-notes"
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          rows={2}
          placeholder="ex. virement reçu le…"
        />
      </div>
      {error && <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{error}</p>}
      <div className="flex justify-end gap-2">
        <Button type="button" size="sm" variant="ghost" onClick={onDone}>
          Annuler
        </Button>
        <Button type="submit" size="sm" disabled={createSubscription.isPending || !planId}>
          {createSubscription.isPending && <LoaderCircle size={14} className="animate-spin" />}
          Enregistrer la période
        </Button>
      </div>
    </form>
  );
}

function PeriodRow({ structureId, period, isCurrent, readOnly }: {
  structureId: number;
  period: Subscription;
  isCurrent: boolean;
  readOnly: boolean;
}) {
  const toggle = useTogglePlatformSubscription(structureId);
  const [error, setError] = useState<string | null>(null);
  const canSuspend = period.status === "essai" || period.status === "active";
  const canResume = period.status === "suspendue";

  function handleToggle(action: "suspend" | "resume") {
    setError(null);
    toggle.mutate({ id: period.id, action }, { onError: (err) => setError(apiErrorMessage(err)) });
  }

  return (
    <div
      className={cn(
        "flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3",
        isCurrent && "bg-accent/5",
      )}
    >
      <div className="min-w-0">
        <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-text">
          <span className="font-medium">{period.plan?.name ?? "Formule"}</span>
          <span className="font-tabular text-text-muted">
            {formatDate(period.starts_at)} → {formatDate(period.ends_at)}
          </span>
          {isCurrent && (
            <Badge status="accent" dot={false}>
              Période courante
            </Badge>
          )}
        </p>
        <p className="text-xs text-text-muted">
          Grâce jusqu'au {formatDate(period.grace_ends_at)}
          {period.created_by_name && <> · saisie par {period.created_by_name}</>}
          {period.notes && <> · {period.notes}</>}
        </p>
        {error && <p className="mt-1 text-xs text-danger">{error}</p>}
      </div>
      <div className="flex shrink-0 items-center gap-2">
        <Badge status={period.status === "suspendue" || period.status === "resiliee" ? "danger" : "neutral"}>
          {STATUS_LABEL[period.status]}
        </Badge>
        {/* Seule la période courante détermine l'état : suspendre une
            période passée ou future n'aurait aucun effet visible. */}
        {!readOnly && isCurrent && (canSuspend || canResume) && (
          <Button
            size="sm"
            variant={canSuspend ? "ghost" : "secondary"}
            onClick={() => handleToggle(canSuspend ? "suspend" : "resume")}
            disabled={toggle.isPending}
          >
            {toggle.isPending && <LoaderCircle size={14} className="animate-spin" />}
            {canSuspend ? "Suspendre" : "Reprendre"}
          </Button>
        )}
      </div>
    </div>
  );
}

/**
 * Abonnements d'une structure : historique des périodes (jamais réécrites)
 * et saisie manuelle d'une nouvelle période. L'état affiché vient du
 * backend, qui applique lui-même la lecture seule.
 */
export function StructureSubscriptionsCard({ structureId, readOnly }: { structureId: number; readOnly: boolean }) {
  const subscriptionsQuery = usePlatformStructureSubscriptions(structureId);
  const [showForm, setShowForm] = useState(false);
  const current = subscriptionsQuery.data ? STATE_BADGE[subscriptionsQuery.data.currentState] : null;

  return (
    <Card>
      <CardHeader className="flex-wrap gap-2">
        <div className="flex flex-wrap items-center gap-3">
          <CardTitle>Abonnement</CardTitle>
          {current && <Badge status={current.status}>{current.label}</Badge>}
        </div>
        {!readOnly && !showForm && (
          <Button size="sm" variant="secondary" onClick={() => setShowForm(true)}>
            <Plus size={14} />
            Nouvelle période
          </Button>
        )}
      </CardHeader>
      <CardContent className="p-0">
        {showForm && <NewPeriodForm structureId={structureId} onDone={() => setShowForm(false)} />}
        {subscriptionsQuery.isLoading ? (
          <div className="p-5">
            <TableSkeleton columns={2} />
          </div>
        ) : subscriptionsQuery.isError ? (
          <div className="p-5">
            <ErrorState message={apiErrorMessage(subscriptionsQuery.error)} onRetry={() => subscriptionsQuery.refetch()} />
          </div>
        ) : !subscriptionsQuery.data || subscriptionsQuery.data.periods.length === 0 ? (
          <EmptyState
            icon={CalendarClock}
            title="Aucune période enregistrée"
            description="Sans période, la structure fonctionne normalement (structures antérieures aux abonnements)."
            className="m-4 py-10"
          />
        ) : (
          <div>
            {subscriptionsQuery.data.periods.map((period) => (
              <PeriodRow
                key={period.id}
                structureId={structureId}
                period={period}
                isCurrent={period.id === subscriptionsQuery.data.currentSubscriptionId}
                readOnly={readOnly}
              />
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
