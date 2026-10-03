import { CalendarCheck, CreditCard, LoaderCircle, Lock } from "lucide-react";
import { useState, type ReactNode } from "react";
import { useSearchParams } from "react-router-dom";
import { PaymentMethods } from "@/components/subscription/payment-methods";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { TableSkeleton } from "@/components/ui/loading-state";
import { useAuth } from "@/hooks/use-auth";
import { useMySubscription, useSubscriptionSelfCheckout } from "@/hooks/use-subscription-payment";
import { apiErrorMessage } from "@/lib/api-error";
import type {
  BillingPeriod,
  MySubscriptionPeriod,
  PaymentTransactionStatus,
  SubscriptionPeriodStatus,
  SubscriptionStateCode,
} from "@/types/api";

/** Mêmes rôles que côté backend (SubscriptionState::ALERTED_ROLES). */
export const SUBSCRIPTION_ROLES = ["administrateur", "direction"];

const STATE_BADGE: Record<SubscriptionStateCode, { label: string; status: "success" | "warning" | "danger" }> = {
  essai_ou_actif: { label: "À jour", status: "success" },
  en_grace: { label: "Délai de grâce", status: "warning" },
  lecture_seule: { label: "Lecture seule", status: "danger" },
};

const STATUS_LABEL: Record<SubscriptionPeriodStatus, string> = {
  essai: "Essai",
  active: "Active",
  suspendue: "Suspendue",
  resiliee: "Résiliée",
};

const PAYMENT_BADGE: Record<PaymentTransactionStatus, { label: string; status: "success" | "warning" | "danger" | "neutral" }> = {
  en_attente: { label: "En attente", status: "warning" },
  complete: { label: "Payé", status: "success" },
  echoue: { label: "Échoué", status: "danger" },
  annule: { label: "Annulé", status: "neutral" },
};

const PERIOD_LABEL: Record<BillingPeriod, string> = { monthly: "mensuel", annual: "annuel" };

function formatDate(value: string): string {
  return new Date(`${value}T00:00:00`).toLocaleDateString("fr-FR");
}

function formatFcfa(value: number): string {
  return `${value.toLocaleString("fr-FR")} FCFA`;
}

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div>
      <dt className="text-xs text-text-muted">{label}</dt>
      <dd className="mt-0.5 text-sm font-medium text-text">{children}</dd>
    </div>
  );
}

function PeriodDetails({ period }: { period: MySubscriptionPeriod }) {
  return (
    <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4">
      <Field label="Formule">{period.plan_name ?? "—"}</Field>
      <Field label="Facturation">{PERIOD_LABEL[period.billing_period]}</Field>
      <Field label="Échéance">{formatDate(period.ends_at)}</Field>
      <Field label="Statut">{STATUS_LABEL[period.status]}</Field>
    </dl>
  );
}

/**
 * « Mon abonnement » : modalités consultables à tout moment et paiement
 * possible en permanence (y compris en avance), pas seulement depuis la
 * bannière d'urgence. Tout est calculé par le backend (GET /subscription).
 */
export function MySubscriptionPage() {
  const query = useMySubscription();
  const checkout = useSubscriptionSelfCheckout();
  const [error, setError] = useState<string | null>(null);
  const [searchParams] = useSearchParams();
  const returned = searchParams.get("paiement");

  function handlePay() {
    setError(null);
    checkout.mutate(undefined, {
      onSuccess: (transaction) => {
        if (transaction.payment_url) {
          window.location.assign(transaction.payment_url);
        } else {
          setError("Le lien de paiement n'a pas pu être obtenu. Réessayez dans un instant.");
        }
      },
      onError: (err) => setError(apiErrorMessage(err)),
    });
  }

  const data = query.data;
  const state = data ? STATE_BADGE[data.state] : null;

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      <div>
        <h1 className="font-heading text-xl font-semibold text-text">Mon abonnement</h1>
        <p className="mt-1 text-sm text-text-muted">Formule, échéance et paiement de l'abonnement de votre structure.</p>
      </div>

      {returned === "succes" && (
        <p role="status" className="rounded-md border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
          Paiement transmis. Votre nouvelle période apparaîtra ici dès la confirmation de DexPay (quelques instants).
        </p>
      )}
      {returned === "echec" && (
        <p role="status" className="rounded-md border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
          Le paiement n'a pas abouti. Aucun montant n'a été validé : vous pouvez réessayer.
        </p>
      )}

      {query.isLoading ? (
        <Card>
          <CardContent className="p-5">
            <TableSkeleton columns={4} />
          </CardContent>
        </Card>
      ) : query.isError ? (
        <ErrorState message={apiErrorMessage(query.error)} onRetry={() => query.refetch()} />
      ) : data ? (
        <>
          <Card>
            <CardHeader className="flex-wrap gap-2">
              <CardTitle>Période en cours</CardTitle>
              {state && data.current && <Badge status={state.status}>{state.label}</Badge>}
            </CardHeader>
            <CardContent className="space-y-4">
              {data.current ? (
                <>
                  <PeriodDetails period={data.current} />
                  <p className="text-xs text-text-muted">
                    Du {formatDate(data.current.starts_at)} au {formatDate(data.current.ends_at)} · délai de grâce
                    jusqu'au {formatDate(data.current.grace_ends_at)}
                  </p>
                </>
              ) : (
                <p className="text-sm text-text-muted">Aucune période d'abonnement n'est enregistrée pour votre structure.</p>
              )}
              {data.upcoming && (
                <div className="rounded-md border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
                  <CalendarCheck size={14} className="mr-1.5 inline" />
                  Période suivante déjà réglée : {data.upcoming.plan_name} du {formatDate(data.upcoming.starts_at)} au{" "}
                  {formatDate(data.upcoming.ends_at)}.
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Renouvellement</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              {data.renewal ? (
                <>
                  <dl className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <Field label="Formule">
                      {data.renewal.plan_name} ({PERIOD_LABEL[data.renewal.period]})
                    </Field>
                    <Field label="Montant">{formatFcfa(data.renewal.amount)}</Field>
                    <Field label="Nouvelle période">
                      {formatDate(data.renewal.starts_at)} → {formatDate(data.renewal.ends_at)}
                    </Field>
                  </dl>
                  <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
                    <PaymentMethods />
                    <Button onClick={handlePay} disabled={checkout.isPending || checkout.isSuccess}>
                      {checkout.isPending || checkout.isSuccess ? (
                        <LoaderCircle size={14} className="animate-spin" />
                      ) : (
                        <CreditCard size={14} />
                      )}
                      Payer {formatFcfa(data.renewal.amount)}
                    </Button>
                  </div>
                  <p className="text-xs text-text-muted">
                    Payer avant l'échéance ne fait rien perdre : la nouvelle période commence le lendemain de la fin de la
                    période en cours.
                  </p>
                  {error && (
                    <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{error}</p>
                  )}
                </>
              ) : (
                <p className="text-sm text-text-muted">{data.unavailable_reason}</p>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Derniers paiements</CardTitle>
            </CardHeader>
            <CardContent className="p-0">
              {data.payments.length === 0 ? (
                <EmptyState
                  icon={CreditCard}
                  title="Aucun paiement en ligne"
                  description="Les paiements effectués via DexPay apparaîtront ici."
                  className="m-4 py-8"
                />
              ) : (
                data.payments.map((payment) => {
                  const badge = PAYMENT_BADGE[payment.status];
                  return (
                    <div
                      key={payment.id}
                      className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3 first:border-t-0"
                    >
                      <div className="min-w-0">
                        <p className="text-sm font-medium text-text">
                          {formatFcfa(payment.amount)}{" "}
                          <span className="font-normal text-text-muted">
                            · {payment.plan_name ?? "Formule"} ({PERIOD_LABEL[payment.period]})
                          </span>
                        </p>
                        <p className="break-all text-xs text-text-muted">
                          {payment.reference} · {new Date(payment.created_at).toLocaleString("fr-FR", { dateStyle: "short", timeStyle: "short" })}
                        </p>
                      </div>
                      <Badge status={badge.status}>{badge.label}</Badge>
                    </div>
                  );
                })
              )}
            </CardContent>
          </Card>
        </>
      ) : null}
    </div>
  );
}

export function MySubscriptionRoute() {
  const { user } = useAuth();
  if (!user?.roles.some((role) => SUBSCRIPTION_ROLES.includes(role))) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="L'abonnement est consultable par l'administrateur et la direction de la structure."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <MySubscriptionPage />;
}
