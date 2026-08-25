import { ArrowLeft } from "lucide-react";
import { Link, useParams } from "react-router-dom";
import { Badge, type BadgeProps } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { ErrorState } from "@/components/ui/error-state";
import { TableSkeleton } from "@/components/ui/loading-state";
import { usePortalInvoice } from "@/hooks/portal/use-portal-invoices";
import { formatDate } from "@/lib/datetime";
import { formatFcfa } from "@/lib/format";
import { portalErrorMessage } from "@/lib/portal-error";
import type { InvoiceStatus } from "@/types/api";

const STATUS_META: Record<InvoiceStatus, { label: string; status: BadgeProps["status"] }> = {
  brouillon: { label: "Brouillon", status: "neutral" },
  emise: { label: "En attente", status: "warning" },
  partiellement_payee: { label: "Partiellement payée", status: "warning" },
  payee: { label: "Payée", status: "success" },
  annulee: { label: "Annulée", status: "neutral" },
};

export function PortalInvoiceDetailPage() {
  const { id } = useParams<{ id: string }>();
  const invoiceQuery = usePortalInvoice(Number(id));

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Link to="/portail/factures" className="text-text-muted hover:text-text">
          <ArrowLeft size={18} />
        </Link>
        <h2 className="font-heading text-lg font-semibold text-text">Détail de la facture</h2>
      </div>

      {invoiceQuery.isError ? (
        <ErrorState message={portalErrorMessage(invoiceQuery.error)} onRetry={() => invoiceQuery.refetch()} />
      ) : invoiceQuery.isLoading ? (
        <TableSkeleton rows={5} columns={3} />
      ) : (
        <>
          <Card>
            <CardContent className="space-y-4 pt-5">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <p className="text-sm font-medium text-text">{invoiceQuery.data!.numero}</p>
                  <p className="text-xs text-text-muted">Émise le {formatDate(invoiceQuery.data!.date_emission)}</p>
                </div>
                <Badge status={STATUS_META[invoiceQuery.data!.statut].status}>
                  {STATUS_META[invoiceQuery.data!.statut].label}
                </Badge>
              </div>

              <div className="grid grid-cols-3 gap-3 border-t border-border pt-4 text-sm">
                <div>
                  <p className="text-xs text-text-muted">Montant total</p>
                  <p className="font-medium text-text">{formatFcfa(invoiceQuery.data!.montant_total)}</p>
                </div>
                <div>
                  <p className="text-xs text-text-muted">Part organisme</p>
                  <p className="font-medium text-text">{formatFcfa(invoiceQuery.data!.montant_part_assurance)}</p>
                </div>
                <div>
                  <p className="text-xs text-text-muted">Part patient</p>
                  <p className="font-medium text-text">{formatFcfa(invoiceQuery.data!.montant_part_patient)}</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="space-y-3 pt-5">
              <h3 className="text-sm font-medium text-text">Prestations</h3>
              <div className="space-y-2">
                {(invoiceQuery.data!.items ?? []).map((item) => (
                  <div key={item.id} className="flex items-center justify-between gap-4 rounded-md border border-border bg-surface px-4 py-3">
                    <div className="min-w-0">
                      <p className="truncate text-sm text-text">{item.libelle}</p>
                      <p className="text-xs text-text-subtle">Quantité : {item.quantite}</p>
                    </div>
                    <p className="text-sm text-text">{formatFcfa(item.montant_total)}</p>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>

          {(invoiceQuery.data!.payments ?? []).length > 0 && (
            <Card>
              <CardContent className="space-y-3 pt-5">
                <h3 className="text-sm font-medium text-text">Paiements</h3>
                <div className="space-y-2">
                  {invoiceQuery.data!.payments!.map((payment) => (
                    <div key={payment.id} className="flex items-center justify-between gap-4 rounded-md border border-border bg-surface px-4 py-3">
                      <div>
                        <p className="text-sm text-text">{payment.mode_paiement}</p>
                        {payment.paid_at && <p className="text-xs text-text-subtle">{formatDate(payment.paid_at)}</p>}
                      </div>
                      <p className="text-sm text-text">{formatFcfa(payment.montant)}</p>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          )}
        </>
      )}
    </div>
  );
}
