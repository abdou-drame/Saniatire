import { History, Receipt, Wallet } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Skeleton } from "@/components/ui/loading-state";
import { CloseSessionDialog } from "@/components/caisse/close-session-dialog";
import { OpenSessionDialog } from "@/components/caisse/open-session-dialog";
import { RecordPaymentDialog } from "@/components/caisse/record-payment-dialog";
import { useAuth } from "@/hooks/use-auth";
import { useCashSessions, useCurrentCashSession } from "@/hooks/use-cash-sessions";
import { usePayments } from "@/hooks/use-payments";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDateTime } from "@/lib/datetime";
import { CASH_SESSION_STATUS_BADGE, CASH_SESSION_STATUS_LABEL, MODE_PAIEMENT_LABEL } from "@/pages/caisse/caisse-status";
import type { CashSession, Payment } from "@/types/api";

/**
 * Écran Caisse. Le backend est seul décideur de l'écart (ecart) calculé à la
 * clôture d'une session — ce composant et ses enfants n'effectuent jamais ce
 * calcul, ils affichent uniquement les valeurs renvoyées par l'API.
 */
export function CaissePage() {
  const { hasPermission } = useAuth();

  const [openSessionDialogOpen, setOpenSessionDialogOpen] = useState(false);
  const [closeSessionDialogOpen, setCloseSessionDialogOpen] = useState(false);
  const [recordPaymentOpen, setRecordPaymentOpen] = useState(false);

  const canView = hasPermission("caisse.view");
  const canCreate = hasPermission("caisse.create");
  const canUpdate = hasPermission("caisse.update");
  const canEncaisser = hasPermission("caisse.encaisser");

  const currentSessionQuery = useCurrentCashSession();
  const closedSessionsQuery = useCashSessions({ statut: "fermee" });

  const session = currentSessionQuery.data;

  const sessionPaymentsQuery = usePayments(session ? { cash_session_id: session.id } : {});

  const paymentColumns: DataTableColumn<Payment>[] = [
    {
      key: "paid_at",
      header: "Heure",
      accessor: (row) => row.paid_at ?? row.created_at,
      render: (row) => (row.paid_at ? formatDateTime(row.paid_at) : "—"),
    },
    { key: "facture", header: "Facture", render: (row) => row.invoice?.numero ?? `#${row.invoice_id}` },
    {
      key: "mode",
      header: "Mode",
      render: (row) => MODE_PAIEMENT_LABEL[row.mode_paiement],
    },
    {
      key: "montant",
      header: "Montant",
      align: "right",
      accessor: (row) => row.montant,
      render: (row) => Number(row.montant).toLocaleString("fr-FR"),
    },
    { key: "numero_recu", header: "N° reçu", render: (row) => row.numero_recu ?? "—" },
  ];

  const closedSessionColumns: DataTableColumn<CashSession>[] = [
    { key: "caissier", header: "Caissier", render: (row) => row.caissier_label ?? `#${row.caissier_id}` },
    { key: "site", header: "Site", render: (row) => row.site?.name ?? "—" },
    { key: "ouverte_le", header: "Ouverte le", render: (row) => formatDateTime(row.ouverte_le) },
    {
      key: "fermee_le",
      header: "Fermée le",
      render: (row) => (row.fermee_le ? formatDateTime(row.fermee_le) : "—"),
    },
    {
      key: "montant_ouverture",
      header: "Montant ouverture",
      align: "right",
      accessor: (row) => row.montant_ouverture,
      render: (row) => Number(row.montant_ouverture).toLocaleString("fr-FR"),
    },
    {
      key: "montant_cloture",
      header: "Montant clôture",
      align: "right",
      accessor: (row) => row.montant_cloture ?? 0,
      render: (row) => (row.montant_cloture !== null ? Number(row.montant_cloture).toLocaleString("fr-FR") : "—"),
    },
    {
      key: "ecart",
      header: "Écart",
      align: "right",
      accessor: (row) => row.ecart ?? 0,
      render: (row) => {
        if (row.ecart === null) return "—";
        // Lecture purement cosmétique du signe renvoyé par le serveur — aucun recalcul.
        const tone = row.ecart > 0 ? "text-success" : row.ecart < 0 ? "text-danger" : "text-text-muted";
        return <span className={`font-tabular ${tone}`}>{Number(row.ecart).toLocaleString("fr-FR")}</span>;
      },
    },
    {
      key: "statut",
      header: "Statut",
      render: (row) => <Badge status={CASH_SESSION_STATUS_BADGE[row.statut]}>{CASH_SESSION_STATUS_LABEL[row.statut]}</Badge>,
    },
  ];

  if (!canView) {
    return (
      <ErrorState message="Vous n'avez pas les permissions nécessaires pour accéder à la caisse." />
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="font-heading text-xl font-semibold text-text">Caisse</h1>
          <p className="mt-1 text-sm text-text-muted">
            Sessions de caisse et encaissements des factures.
          </p>
        </div>
        {canEncaisser && (
          <Button size="sm" onClick={() => setRecordPaymentOpen(true)}>
            <Receipt size={14} />
            Enregistrer un paiement
          </Button>
        )}
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Session en cours</CardTitle>
          {session && canUpdate && (
            <Button size="sm" variant="secondary" onClick={() => setCloseSessionDialogOpen(true)}>
              Clôturer la session
            </Button>
          )}
        </CardHeader>
        <CardContent className="space-y-4">
          {currentSessionQuery.isLoading ? (
            <Skeleton className="h-20 w-full" />
          ) : currentSessionQuery.isError ? (
            <ErrorState
              message={apiErrorMessage(currentSessionQuery.error)}
              onRetry={() => currentSessionQuery.refetch()}
            />
          ) : session ? (
            <>
              <div className="flex flex-wrap items-center gap-6 rounded-md border border-border bg-surface-hover/50 px-4 py-3">
                <div>
                  <p className="text-xs text-text-subtle">Site</p>
                  <p className="text-sm text-text">{session.site?.name ?? "—"}</p>
                </div>
                <div>
                  <p className="text-xs text-text-subtle">Ouverte le</p>
                  <p className="text-sm text-text">{formatDateTime(session.ouverte_le)}</p>
                </div>
                <div>
                  <p className="text-xs text-text-subtle">Montant d'ouverture</p>
                  <p className="font-tabular text-sm text-text">
                    {Number(session.montant_ouverture).toLocaleString("fr-FR")}
                  </p>
                </div>
                <Badge status={CASH_SESSION_STATUS_BADGE[session.statut]}>
                  {CASH_SESSION_STATUS_LABEL[session.statut]}
                </Badge>
              </div>

              <div>
                <p className="mb-2 text-xs font-medium uppercase tracking-wide text-text-subtle">
                  Paiements de cette session
                </p>
                {sessionPaymentsQuery.isError ? (
                  <ErrorState
                    message={apiErrorMessage(sessionPaymentsQuery.error)}
                    onRetry={() => sessionPaymentsQuery.refetch()}
                  />
                ) : (
                  <DataTable
                    columns={paymentColumns}
                    data={sessionPaymentsQuery.data?.data ?? []}
                    rowKey={(row) => row.id}
                    isLoading={sessionPaymentsQuery.isLoading}
                    emptyState={
                      <EmptyState
                        icon={Receipt}
                        title="Aucun paiement"
                        description="Aucun paiement n'a encore été enregistré sur cette session."
                      />
                    }
                  />
                )}
              </div>
            </>
          ) : (
            <EmptyState
              icon={Wallet}
              title="Aucune session de caisse ouverte"
              description="Ouvrez une session pour commencer à encaisser des paiements en espèces."
              actionLabel={canCreate ? "Ouvrir une session" : undefined}
              onAction={canCreate ? () => setOpenSessionDialogOpen(true) : undefined}
            />
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Historique des sessions clôturées</CardTitle>
        </CardHeader>
        <CardContent>
          {closedSessionsQuery.isError ? (
            <ErrorState
              message={apiErrorMessage(closedSessionsQuery.error)}
              onRetry={() => closedSessionsQuery.refetch()}
            />
          ) : (
            <DataTable
              columns={closedSessionColumns}
              data={closedSessionsQuery.data?.data ?? []}
              rowKey={(row) => row.id}
              isLoading={closedSessionsQuery.isLoading}
              emptyState={
                <EmptyState
                  icon={History}
                  title="Aucune session clôturée"
                  description="L'historique des sessions de caisse clôturées apparaîtra ici."
                />
              }
            />
          )}
        </CardContent>
      </Card>

      <OpenSessionDialog open={openSessionDialogOpen} onOpenChange={setOpenSessionDialogOpen} />
      {session && (
        <CloseSessionDialog
          open={closeSessionDialogOpen}
          onOpenChange={setCloseSessionDialogOpen}
          session={session}
        />
      )}
      <RecordPaymentDialog open={recordPaymentOpen} onOpenChange={setRecordPaymentOpen} />
    </div>
  );
}
