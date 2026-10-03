import axios from "axios";
import { Check, Copy, CreditCard, ExternalLink, LoaderCircle, Link2, Mail } from "lucide-react";
import { useState, type FormEvent } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Label } from "@/components/ui/label";
import { TableSkeleton } from "@/components/ui/loading-state";
import { Select } from "@/components/ui/select";
import {
  useCreateDexPayCheckout,
  usePlatformPaymentTransactions,
  usePlatformPlans,
  useSendDexPayLinkEmail,
} from "@/hooks/use-platform-subscriptions";
import { apiErrorMessage } from "@/lib/api-error";
import type { BillingPeriod, PaymentTransaction, PaymentTransactionStatus } from "@/types/api";

const STATUS_BADGE: Record<PaymentTransactionStatus, { label: string; status: "success" | "warning" | "danger" | "neutral" }> = {
  en_attente: { label: "En attente", status: "warning" },
  complete: { label: "Payé", status: "success" },
  echoue: { label: "Échoué", status: "danger" },
  annule: { label: "Annulé", status: "neutral" },
};

const PERIOD_LABEL: Record<BillingPeriod, string> = { monthly: "Mensuel", annual: "Annuel" };

function formatFcfa(value: number): string {
  return `${value.toLocaleString("fr-FR")} FCFA`;
}

function formatDateTime(value: string): string {
  return new Date(value).toLocaleString("fr-FR", { dateStyle: "short", timeStyle: "short" });
}

/** Un 503 de l'envoi d'email porte un message précis (SMTP indisponible) que le libellé générique 5xx masquerait. */
function sendEmailErrorMessage(error: unknown): string {
  if (axios.isAxiosError(error) && error.response?.status === 503) {
    const message = (error.response.data as { message?: string } | undefined)?.message;
    if (message) return message;
  }
  return apiErrorMessage(error);
}

function PaymentLinkPanel({ structureId, transaction }: { structureId: number; transaction: PaymentTransaction }) {
  const [copied, setCopied] = useState(false);
  const sendEmail = useSendDexPayLinkEmail(structureId);
  const [emailError, setEmailError] = useState<string | null>(null);
  const url = transaction.payment_url ?? "";

  function handleSendEmail() {
    setEmailError(null);
    sendEmail.mutate(transaction.id, { onError: (err) => setEmailError(sendEmailErrorMessage(err)) });
  }

  async function handleCopy() {
    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
    } catch {
      // Presse-papiers indisponible : le lien reste affiché et sélectionnable.
    }
  }

  return (
    <div className="space-y-2 rounded-md border border-success/30 bg-success/10 p-3">
      <p className="text-xs font-medium text-success">
        Lien créé ({formatFcfa(transaction.amount)}, {PERIOD_LABEL[transaction.period].toLowerCase()}) — à transmettre
        au client. La période d'abonnement sera créée automatiquement dès la confirmation du paiement.
      </p>
      <code className="block break-all rounded border border-border bg-bg px-2 py-1.5 text-xs text-text">{url}</code>
      <div className="flex flex-wrap gap-2">
        <Button type="button" size="sm" variant="secondary" onClick={handleCopy}>
          {copied ? <Check size={14} /> : <Copy size={14} />}
          {copied ? "Copié" : "Copier le lien"}
        </Button>
        <Button type="button" size="sm" variant="ghost" onClick={() => window.open(url, "_blank", "noopener,noreferrer")}>
          <ExternalLink size={14} />
          Ouvrir
        </Button>
        <Button
          type="button"
          size="sm"
          variant="secondary"
          onClick={handleSendEmail}
          disabled={sendEmail.isPending || sendEmail.isSuccess}
        >
          {sendEmail.isPending ? (
            <LoaderCircle size={14} className="animate-spin" />
          ) : sendEmail.isSuccess ? (
            <Check size={14} />
          ) : (
            <Mail size={14} />
          )}
          {sendEmail.isSuccess ? "Envoyé" : "Envoyer par email"}
        </Button>
      </div>
      {sendEmail.data && (
        <p role="status" className="text-xs text-success">
          Lien envoyé à {sendEmail.data.sent_to}.
        </p>
      )}
      {emailError && (
        <p role="alert" className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
          {emailError}
        </p>
      )}
    </div>
  );
}

function CheckoutForm({ structureId, onDone }: { structureId: number; onDone: () => void }) {
  const plansQuery = usePlatformPlans();
  const checkout = useCreateDexPayCheckout(structureId);
  const [planId, setPlanId] = useState("");
  const [period, setPeriod] = useState<BillingPeriod>("monthly");
  const [error, setError] = useState<string | null>(null);

  // Seules les formules avec un tarif pour la périodicité choisie sont
  // payables en ligne (Enterprise reste sur devis).
  const payablePlans = (plansQuery.data ?? []).filter(
    (plan) => plan.is_active && (period === "annual" ? plan.annual_price_fcfa : plan.monthly_price_fcfa),
  );

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    checkout.mutate(
      { plan_id: Number(planId), period },
      { onError: (err) => setError(apiErrorMessage(err)) },
    );
  }

  if (checkout.data) {
    return (
      <div className="space-y-3 border-t border-border bg-surface-hover/30 p-4 sm:p-5">
        <PaymentLinkPanel structureId={structureId} transaction={checkout.data} />
        <div className="flex justify-end">
          <Button type="button" size="sm" variant="ghost" onClick={onDone}>
            Fermer
          </Button>
        </div>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-3 border-t border-border bg-surface-hover/30 p-4 sm:p-5">
      <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Lien de paiement DexPay</p>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
          <Label htmlFor="dexpay-period">Périodicité</Label>
          <Select
            id="dexpay-period"
            value={period}
            onChange={(e) => {
              setPeriod(e.target.value as BillingPeriod);
              setPlanId("");
            }}
          >
            <option value="monthly">Mensuelle</option>
            <option value="annual">Annuelle</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="dexpay-plan">Formule</Label>
          <Select id="dexpay-plan" value={planId} onChange={(e) => setPlanId(e.target.value)} required>
            <option value="" disabled>
              {plansQuery.isLoading ? "Chargement…" : "Choisir une formule"}
            </option>
            {payablePlans.map((plan) => (
              <option key={plan.id} value={plan.id}>
                {plan.name} · {formatFcfa((period === "annual" ? plan.annual_price_fcfa : plan.monthly_price_fcfa) ?? 0)}
              </option>
            ))}
          </Select>
        </div>
      </div>
      <p className="text-xs text-text-muted">
        Le montant est celui de la grille des formules. Paiement Wave ou Orange Money via DexPay.
      </p>
      {error && <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{error}</p>}
      <div className="flex justify-end gap-2">
        <Button type="button" size="sm" variant="ghost" onClick={onDone}>
          Annuler
        </Button>
        <Button type="submit" size="sm" disabled={checkout.isPending || !planId}>
          {checkout.isPending && <LoaderCircle size={14} className="animate-spin" />}
          Générer le lien
        </Button>
      </div>
    </form>
  );
}

/**
 * Paiements en ligne d'une structure : génération d'un lien DexPay par
 * l'administration plateforme et historique de toutes les sessions (y
 * compris celles ouvertes par la structure elle-même en self-service).
 */
export function StructurePaymentsCard({ structureId, readOnly }: { structureId: number; readOnly: boolean }) {
  const transactionsQuery = usePlatformPaymentTransactions(structureId);
  const [showForm, setShowForm] = useState(false);

  return (
    <Card>
      <CardHeader className="flex-wrap gap-2">
        <CardTitle>Paiements en ligne</CardTitle>
        {!readOnly && !showForm && (
          <Button size="sm" variant="secondary" onClick={() => setShowForm(true)}>
            <Link2 size={14} />
            Générer un lien de paiement DexPay
          </Button>
        )}
      </CardHeader>
      <CardContent className="p-0">
        {showForm && <CheckoutForm structureId={structureId} onDone={() => setShowForm(false)} />}
        {transactionsQuery.isLoading ? (
          <div className="p-5">
            <TableSkeleton columns={4} />
          </div>
        ) : transactionsQuery.isError ? (
          <div className="p-5">
            <ErrorState message={apiErrorMessage(transactionsQuery.error)} onRetry={() => transactionsQuery.refetch()} />
          </div>
        ) : !transactionsQuery.data || transactionsQuery.data.length === 0 ? (
          <EmptyState
            icon={CreditCard}
            title="Aucun paiement en ligne"
            description="Les liens DexPay générés ici ou par la structure apparaîtront dans cet historique."
            className="m-4 py-10"
          />
        ) : (
          <div>
            {transactionsQuery.data.map((transaction) => {
              const badge = STATUS_BADGE[transaction.status];
              return (
                <div
                  key={transaction.id}
                  className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3"
                >
                  <div className="min-w-0">
                    <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-text">
                      <span className="font-medium font-tabular">{formatFcfa(transaction.amount)}</span>
                      <span className="text-text-muted">
                        {transaction.plan_name ?? "Formule"} · {PERIOD_LABEL[transaction.period]}
                      </span>
                    </p>
                    <p className="break-all text-xs text-text-muted">
                      {transaction.reference} · {formatDateTime(transaction.created_at)} ·{" "}
                      {transaction.origin === "structure_admin" ? "Payé par la structure" : "Lien plateforme"}
                    </p>
                  </div>
                  <Badge status={badge.status}>{badge.label}</Badge>
                </div>
              );
            })}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
