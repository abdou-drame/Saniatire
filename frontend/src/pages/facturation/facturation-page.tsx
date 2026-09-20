import { FileText, LoaderCircle, Receipt } from "lucide-react";
import { useState } from "react";
import { CreateInvoiceDialog } from "@/components/facturation/create-invoice-dialog";
import { CreateQuoteDialog } from "@/components/facturation/create-quote-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Skeleton } from "@/components/ui/loading-state";
import { useAuth } from "@/hooks/use-auth";
import { useCancelInvoice, useEmitInvoice, useInvoice, useInvoices } from "@/hooks/use-invoices";
import { useCancelQuote, useConvertQuote, useQuote, useQuotes } from "@/hooks/use-quotes";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import {
  INVOICE_STATUS_BADGE,
  INVOICE_STATUS_LABEL,
  QUOTE_STATUS_BADGE,
  QUOTE_STATUS_LABEL,
} from "@/pages/facturation/facturation-status";
import { ServiceTariffsSection } from "@/pages/facturation/service-tariffs-section";
import type { Invoice, Quote } from "@/types/api";

function patientLabel(patient: Invoice["patient"] | Quote["patient"]) {
  if (!patient) return "—";
  return `${patient.first_name} ${patient.last_name}`;
}

/**
 * Écran Facturation (factures + devis). Le backend est la seule source de
 * vérité pour tout calcul financier (montant_total, répartition
 * assurance/patient, répartition à la conversion d'un devis) — ce composant
 * ne recalcule ni ne devine jamais un montant : il gate les actions par
 * permission + statut réel renvoyé par l'API, envoie les mutations, et
 * affiche telle quelle toute erreur 422/403.
 */
export function FacturationPage() {
  const { hasPermission } = useAuth();

  const [createInvoiceOpen, setCreateInvoiceOpen] = useState(false);
  const [createQuoteOpen, setCreateQuoteOpen] = useState(false);
  const [selectedInvoiceId, setSelectedInvoiceId] = useState<number | null>(null);
  const [selectedQuoteId, setSelectedQuoteId] = useState<number | null>(null);

  const canCreate = hasPermission("facturation.create");

  const invoicesQuery = useInvoices();
  const quotesQuery = useQuotes();

  const invoiceColumns: DataTableColumn<Invoice>[] = [
    { key: "numero", header: "Numéro", accessor: (row) => row.numero },
    { key: "patient", header: "Patient", render: (row) => patientLabel(row.patient) },
    { key: "site", header: "Site", render: (row) => row.site?.name ?? "—" },
    {
      key: "montant_total",
      header: "Montant total",
      align: "right",
      accessor: (row) => row.montant_total,
      render: (row) => Number(row.montant_total).toLocaleString("fr-FR"),
    },
    {
      key: "montant_part_patient",
      header: "Part patient",
      align: "right",
      accessor: (row) => row.montant_part_patient,
      render: (row) => Number(row.montant_part_patient).toLocaleString("fr-FR"),
    },
    {
      key: "montant_part_assurance",
      header: "Part assurance",
      align: "right",
      accessor: (row) => row.montant_part_assurance,
      render: (row) => Number(row.montant_part_assurance).toLocaleString("fr-FR"),
    },
    {
      key: "statut",
      header: "Statut",
      render: (row) => <Badge status={INVOICE_STATUS_BADGE[row.statut]}>{INVOICE_STATUS_LABEL[row.statut]}</Badge>,
    },
    { key: "date_emission", header: "Date d'émission", render: (row) => formatDate(row.date_emission) },
  ];

  const quoteColumns: DataTableColumn<Quote>[] = [
    { key: "numero", header: "Numéro", accessor: (row) => row.numero },
    { key: "patient", header: "Patient", render: (row) => patientLabel(row.patient) },
    { key: "site", header: "Site", render: (row) => row.site?.name ?? "—" },
    {
      key: "montant_total",
      header: "Montant total",
      align: "right",
      accessor: (row) => row.montant_total,
      render: (row) => Number(row.montant_total).toLocaleString("fr-FR"),
    },
    {
      key: "statut",
      header: "Statut",
      render: (row) => <Badge status={QUOTE_STATUS_BADGE[row.statut]}>{QUOTE_STATUS_LABEL[row.statut]}</Badge>,
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="font-heading text-xl font-semibold text-text">Facturation</h1>
          <p className="mt-1 text-sm text-text-muted">Factures et devis des patients.</p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Factures</CardTitle>
          {canCreate && (
            <Button size="sm" onClick={() => setCreateInvoiceOpen(true)}>
              <Receipt size={14} />
              Nouvelle facture
            </Button>
          )}
        </CardHeader>
        <CardContent className="space-y-4">
          {invoicesQuery.isError ? (
            <ErrorState message={apiErrorMessage(invoicesQuery.error)} onRetry={() => invoicesQuery.refetch()} />
          ) : (
            <DataTable
              columns={invoiceColumns}
              data={invoicesQuery.data?.data ?? []}
              rowKey={(row) => row.id}
              isLoading={invoicesQuery.isLoading}
              onRowClick={(row) => setSelectedInvoiceId(row.id)}
              emptyState={
                <EmptyState
                  icon={Receipt}
                  title="Aucune facture"
                  description="Aucune facture n'a encore été créée."
                  actionLabel={canCreate ? "Nouvelle facture" : undefined}
                  onAction={canCreate ? () => setCreateInvoiceOpen(true) : undefined}
                />
              }
            />
          )}

          {selectedInvoiceId && (
            <InvoiceDetail invoiceId={selectedInvoiceId} onClose={() => setSelectedInvoiceId(null)} />
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Devis</CardTitle>
          {canCreate && (
            <Button size="sm" onClick={() => setCreateQuoteOpen(true)}>
              <FileText size={14} />
              Nouveau devis
            </Button>
          )}
        </CardHeader>
        <CardContent className="space-y-4">
          {quotesQuery.isError ? (
            <ErrorState message={apiErrorMessage(quotesQuery.error)} onRetry={() => quotesQuery.refetch()} />
          ) : (
            <DataTable
              columns={quoteColumns}
              data={quotesQuery.data?.data ?? []}
              rowKey={(row) => row.id}
              isLoading={quotesQuery.isLoading}
              onRowClick={(row) => setSelectedQuoteId(row.id)}
              emptyState={
                <EmptyState
                  icon={FileText}
                  title="Aucun devis"
                  description="Aucun devis n'a encore été créé."
                  actionLabel={canCreate ? "Nouveau devis" : undefined}
                  onAction={canCreate ? () => setCreateQuoteOpen(true) : undefined}
                />
              }
            />
          )}

          {selectedQuoteId && (
            <QuoteDetail
              quoteId={selectedQuoteId}
              onClose={() => setSelectedQuoteId(null)}
              onConverted={(invoiceId) => setSelectedInvoiceId(invoiceId)}
            />
          )}
        </CardContent>
      </Card>

      <ServiceTariffsSection />

      <CreateInvoiceDialog open={createInvoiceOpen} onOpenChange={setCreateInvoiceOpen} />
      <CreateQuoteDialog open={createQuoteOpen} onOpenChange={setCreateQuoteOpen} />
    </div>
  );
}

function InvoiceDetail({ invoiceId, onClose }: { invoiceId: number; onClose: () => void }) {
  const { hasPermission } = useAuth();
  const invoiceQuery = useInvoice(invoiceId);

  const [actionError, setActionError] = useState<string | null>(null);

  const emitInvoice = useEmitInvoice();
  const cancelInvoice = useCancelInvoice();

  const canValidate = hasPermission("facturation.validate");
  const canCancel = hasPermission("facturation.cancel");

  if (invoiceQuery.isLoading) {
    return (
      <div className="rounded-md border border-border p-4">
        <Skeleton className="h-5 w-48" />
        <Skeleton className="mt-3 h-24 w-full" />
      </div>
    );
  }

  if (invoiceQuery.isError) {
    return <ErrorState message={apiErrorMessage(invoiceQuery.error)} onRetry={() => invoiceQuery.refetch()} />;
  }

  const invoice = invoiceQuery.data;
  if (!invoice) return null;

  function handleEmit() {
    setActionError(null);
    emitInvoice.mutate(invoiceId, { onError: (err) => setActionError(apiErrorMessage(err)) });
  }

  function handleCancel() {
    setActionError(null);
    cancelInvoice.mutate(invoiceId, { onError: (err) => setActionError(apiErrorMessage(err)) });
  }

  // Le backend est seul décideur : une facture "annulee" ou "payee" ne peut
  // plus être annulée, et seule une facture "brouillon" peut être émise. On
  // ne masque jamais l'action d'encaissement d'un paiement même sur une
  // facture brouillon — cela relève de l'écran Caisse, pas de cet écran.
  const canStillCancel = invoice.statut !== "payee" && invoice.statut !== "annulee";

  return (
    <div className="space-y-4 rounded-md border border-border p-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-text">Facture {invoice.numero}</p>
          <p className="text-xs text-text-muted">
            {patientLabel(invoice.patient)} · {invoice.site?.name ?? "—"}
            {invoice.insurance_convention_label ? ` · ${invoice.insurance_convention_label}` : ""}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Badge status={INVOICE_STATUS_BADGE[invoice.statut]}>{INVOICE_STATUS_LABEL[invoice.statut]}</Badge>
          <Button variant="ghost" size="sm" onClick={onClose}>
            Fermer
          </Button>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-3 rounded-md border border-border bg-surface-hover/40 p-3 text-sm sm:grid-cols-4">
        <div>
          <p className="text-xs text-text-subtle">Montant total</p>
          <p className="font-tabular font-medium text-text">{Number(invoice.montant_total).toLocaleString("fr-FR")}</p>
        </div>
        <div>
          <p className="text-xs text-text-subtle">Part patient</p>
          <p className="font-tabular font-medium text-text">
            {Number(invoice.montant_part_patient).toLocaleString("fr-FR")}
          </p>
        </div>
        <div>
          <p className="text-xs text-text-subtle">Part assurance</p>
          <p className="font-tabular font-medium text-text">
            {Number(invoice.montant_part_assurance).toLocaleString("fr-FR")}
          </p>
        </div>
        <div>
          <p className="text-xs text-text-subtle">Échéance</p>
          <p className="text-text">{invoice.date_echeance ? formatDate(invoice.date_echeance) : "—"}</p>
        </div>
      </div>

      <div className="flex flex-wrap gap-2">
        {invoice.statut === "brouillon" && canValidate && (
          <Button size="sm" onClick={handleEmit} disabled={emitInvoice.isPending}>
            {emitInvoice.isPending && <LoaderCircle size={14} className="animate-spin" />}
            Émettre
          </Button>
        )}
        {canStillCancel && canCancel && (
          <Button size="sm" variant="danger" onClick={handleCancel} disabled={cancelInvoice.isPending}>
            {cancelInvoice.isPending && <LoaderCircle size={14} className="animate-spin" />}
            Annuler
          </Button>
        )}
      </div>

      {actionError && (
        <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
          {actionError}
        </p>
      )}

      <div className="space-y-2">
        <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Lignes</p>
        {!invoice.items || invoice.items.length === 0 ? (
          <p className="text-xs text-text-muted">Aucune ligne sur cette facture.</p>
        ) : (
          <div className="divide-y divide-border rounded-md border border-border">
            {invoice.items.map((item) => (
              <div key={item.id} className="flex flex-wrap items-center justify-between gap-3 px-3 py-2 text-sm">
                <div>
                  <p className="text-text">{item.libelle}</p>
                  <p className="text-xs text-text-muted">
                    {item.categorie ?? "—"} · Qté {item.quantite} × {Number(item.prix_unitaire).toLocaleString("fr-FR")}
                    {item.taux_couverture_applique !== null
                      ? ` · Taux couverture appliqué : ${item.taux_couverture_applique}%`
                      : ""}
                  </p>
                </div>
                <div className="text-right">
                  <p className="font-tabular text-text">{Number(item.montant_total).toLocaleString("fr-FR")}</p>
                  <p className="text-xs text-text-muted">
                    Assurance {Number(item.montant_assurance).toLocaleString("fr-FR")} · Patient{" "}
                    {Number(item.montant_patient).toLocaleString("fr-FR")}
                  </p>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      <div className="space-y-2">
        <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Paiements</p>
        {!invoice.payments || invoice.payments.length === 0 ? (
          <p className="text-xs text-text-muted">Aucun paiement enregistré sur cette facture.</p>
        ) : (
          <div className="divide-y divide-border rounded-md border border-border">
            {invoice.payments.map((payment) => (
              <div key={payment.id} className="flex flex-wrap items-center justify-between gap-3 px-3 py-2 text-sm">
                <div>
                  <p className="text-text">
                    {payment.mode_paiement} {payment.numero_recu ? `· Reçu ${payment.numero_recu}` : ""}
                  </p>
                  <p className="text-xs text-text-muted">
                    {payment.paid_at ? formatDate(payment.paid_at) : "—"}
                    {payment.caissier_label ? ` · ${payment.caissier_label}` : ""}
                  </p>
                </div>
                <p className="font-tabular text-text">{Number(payment.montant).toLocaleString("fr-FR")}</p>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

function QuoteDetail({
  quoteId,
  onClose,
  onConverted,
}: {
  quoteId: number;
  onClose: () => void;
  onConverted: (invoiceId: number) => void;
}) {
  const { hasPermission } = useAuth();
  const quoteQuery = useQuote(quoteId);

  const [actionError, setActionError] = useState<string | null>(null);
  const [convertedInvoice, setConvertedInvoice] = useState<{ id: number } | null>(null);

  const cancelQuote = useCancelQuote();
  const convertQuote = useConvertQuote();

  const canValidate = hasPermission("facturation.validate");
  const canCancel = hasPermission("facturation.cancel");

  if (quoteQuery.isLoading) {
    return (
      <div className="rounded-md border border-border p-4">
        <Skeleton className="h-5 w-48" />
        <Skeleton className="mt-3 h-24 w-full" />
      </div>
    );
  }

  if (quoteQuery.isError) {
    return <ErrorState message={apiErrorMessage(quoteQuery.error)} onRetry={() => quoteQuery.refetch()} />;
  }

  const quote = quoteQuery.data;
  if (!quote) return null;

  function handleConvert() {
    setActionError(null);
    convertQuote.mutate(quoteId, {
      onSuccess: (invoice) => {
        setConvertedInvoice({ id: invoice.id });
        onConverted(invoice.id);
      },
      onError: (err) => setActionError(apiErrorMessage(err)),
    });
  }

  function handleCancel() {
    setActionError(null);
    cancelQuote.mutate(quoteId, { onError: (err) => setActionError(apiErrorMessage(err)) });
  }

  const canStillCancel = quote.statut !== "converti" && quote.statut !== "annule";

  return (
    <div className="space-y-4 rounded-md border border-border p-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-text">Devis {quote.numero}</p>
          <p className="text-xs text-text-muted">
            {patientLabel(quote.patient)} · {quote.site?.name ?? "—"}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Badge status={QUOTE_STATUS_BADGE[quote.statut]}>{QUOTE_STATUS_LABEL[quote.statut]}</Badge>
          <Button variant="ghost" size="sm" onClick={onClose}>
            Fermer
          </Button>
        </div>
      </div>

      <div className="rounded-md border border-border bg-surface-hover/40 p-3 text-sm">
        <p className="text-xs text-text-subtle">Montant total</p>
        <p className="font-tabular font-medium text-text">{Number(quote.montant_total).toLocaleString("fr-FR")}</p>
      </div>

      <div className="flex flex-wrap gap-2">
        {quote.statut === "emis" && canValidate && (
          <Button size="sm" onClick={handleConvert} disabled={convertQuote.isPending}>
            {convertQuote.isPending && <LoaderCircle size={14} className="animate-spin" />}
            Convertir en facture
          </Button>
        )}
        {canStillCancel && canCancel && (
          <Button size="sm" variant="danger" onClick={handleCancel} disabled={cancelQuote.isPending}>
            {cancelQuote.isPending && <LoaderCircle size={14} className="animate-spin" />}
            Annuler
          </Button>
        )}
      </div>

      {convertedInvoice && (
        <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-success/30 bg-success/10 px-3 py-2 text-xs text-success">
          <span>
            Facture #{convertedInvoice.id} créée en brouillon — pensez à l'émettre.
          </span>
          <Button size="sm" variant="secondary" onClick={() => onConverted(convertedInvoice.id)}>
            Voir la facture
          </Button>
        </div>
      )}

      {actionError && (
        <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
          {actionError}
        </p>
      )}

      <div className="space-y-2">
        <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Lignes</p>
        {!quote.items || quote.items.length === 0 ? (
          <p className="text-xs text-text-muted">Aucune ligne sur ce devis.</p>
        ) : (
          <div className="divide-y divide-border rounded-md border border-border">
            {quote.items.map((item) => (
              <div key={item.id} className="flex flex-wrap items-center justify-between gap-3 px-3 py-2 text-sm">
                <div>
                  <p className="text-text">{item.libelle}</p>
                  <p className="text-xs text-text-muted">
                    {item.categorie} · Qté {item.quantite} × {Number(item.prix_unitaire).toLocaleString("fr-FR")}
                  </p>
                </div>
                <p className="font-tabular text-text">{Number(item.montant_total).toLocaleString("fr-FR")}</p>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
