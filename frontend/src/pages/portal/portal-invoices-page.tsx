import { Receipt } from "lucide-react";
import { Link } from "react-router-dom";
import { Badge, type BadgeProps } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { TableSkeleton } from "@/components/ui/loading-state";
import { usePortalInvoices, usePortalSolde } from "@/hooks/portal/use-portal-invoices";
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

export function PortalInvoicesPage() {
  const invoicesQuery = usePortalInvoices();
  const soldeQuery = usePortalSolde();

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-3">
        <h2 className="font-heading text-lg font-semibold text-text">Mes factures</h2>
        {!soldeQuery.isLoading && !soldeQuery.isError && (
          <div className="text-right">
            <p className="text-xs text-text-muted">Solde dû</p>
            <p className="text-base font-semibold text-text">{formatFcfa(soldeQuery.data ?? 0)}</p>
          </div>
        )}
      </div>

      {invoicesQuery.isError ? (
        <ErrorState message={portalErrorMessage(invoicesQuery.error)} onRetry={() => invoicesQuery.refetch()} />
      ) : invoicesQuery.isLoading ? (
        <TableSkeleton rows={4} columns={3} />
      ) : invoicesQuery.data!.length === 0 ? (
        <EmptyState icon={Receipt} title="Aucune facture" description="Vos factures apparaîtront ici." />
      ) : (
        <Card>
          <CardContent className="space-y-2 pt-5">
            {invoicesQuery.data!.map((invoice) => {
              const meta = STATUS_META[invoice.statut];
              return (
                <Link
                  key={invoice.id}
                  to={`/portail/factures/${invoice.id}`}
                  className="flex items-center justify-between gap-4 rounded-md border border-border bg-surface px-4 py-3 transition-colors hover:bg-surface-hover"
                >
                  <div className="min-w-0">
                    <p className="text-sm font-medium text-text">{invoice.numero}</p>
                    <p className="text-xs text-text-muted">{formatDate(invoice.date_emission)}</p>
                  </div>
                  <div className="flex items-center gap-3">
                    <p className="text-sm text-text">{formatFcfa(invoice.montant_total)}</p>
                    <Badge status={meta.status}>{meta.label}</Badge>
                  </div>
                </Link>
              );
            })}
          </CardContent>
        </Card>
      )}
    </div>
  );
}
