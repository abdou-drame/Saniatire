import { LoaderCircle, Package, Plus, ShoppingCart } from "lucide-react";
import { useState } from "react";
import { Badge, type BadgeProps } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Skeleton } from "@/components/ui/loading-state";
import { CreatePurchaseOrderDialog } from "@/components/achats/create-purchase-order-dialog";
import { CreatePurchaseRequestDialog } from "@/components/achats/create-purchase-request-dialog";
import { RecordReceptionDialog } from "@/components/achats/record-reception-dialog";
import { useAuth } from "@/hooks/use-auth";
import {
  useApprovePurchaseOrder,
  useApprovePurchaseRequest,
  useCancelPurchaseOrder,
  usePurchaseOrder,
  usePurchaseOrders,
  usePurchaseRequests,
  useRejectPurchaseRequest,
  useSubmitPurchaseOrder,
} from "@/hooks/use-purchase-orders";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import { ApprovalRulesSection } from "@/pages/achats/approval-rules-section";
import { FournisseursSection } from "@/pages/achats/fournisseurs-section";
import { ORDER_STATUS_BADGE, ORDER_STATUS_LABEL, REQUEST_STATUS_BADGE, REQUEST_STATUS_LABEL } from "@/pages/achats/achats-status";
import type { PurchaseOrder, PurchaseOrderApprovalStatut, PurchaseOrderItem, PurchaseRequest } from "@/types/api";

const APPROVAL_STATUS_LABEL: Record<PurchaseOrderApprovalStatut, string> = {
  en_attente: "En attente",
  validee: "Validée",
};

const APPROVAL_STATUS_BADGE: Record<PurchaseOrderApprovalStatut, NonNullable<BadgeProps["status"]>> = {
  en_attente: "warning",
  validee: "success",
};

/**
 * Écran Achats/Fournisseurs. Le backend est la seule source de vérité pour
 * toute règle métier (statuts, niveau de validation résolu dynamiquement à
 * partir des approval_rules croisées avec le rôle Spatie de l'utilisateur) —
 * ce composant ne recalcule ni ne devine jamais si une action est autorisée :
 * il gate uniquement par permission + statut réel renvoyé par l'API, envoie
 * les mutations, et affiche toute erreur 422/403 telle quelle.
 */
export function AchatsPage() {
  const { hasPermission } = useAuth();

  const [createRequestOpen, setCreateRequestOpen] = useState(false);
  const [createOrderOpen, setCreateOrderOpen] = useState(false);
  const [selectedOrderId, setSelectedOrderId] = useState<number | null>(null);
  const [requestActionError, setRequestActionError] = useState<string | null>(null);

  const canCreate = hasPermission("achats.create");
  const canValidateRequests = hasPermission("achats.validate");

  const requestsQuery = usePurchaseRequests();
  const ordersQuery = usePurchaseOrders();

  const approveRequest = useApprovePurchaseRequest();
  const rejectRequest = useRejectPurchaseRequest();

  function handleApproveRequest(id: number) {
    setRequestActionError(null);
    approveRequest.mutate(id, { onError: (err) => setRequestActionError(apiErrorMessage(err)) });
  }

  function handleRejectRequest(id: number) {
    setRequestActionError(null);
    rejectRequest.mutate(id, { onError: (err) => setRequestActionError(apiErrorMessage(err)) });
  }

  const requestColumns: DataTableColumn<PurchaseRequest>[] = [
    { key: "product", header: "Produit", render: (row) => row.product?.nom_commercial ?? "—" },
    { key: "site", header: "Site", render: (row) => row.site?.name ?? "—" },
    { key: "quantite", header: "Quantité", align: "right", accessor: (row) => row.quantite },
    { key: "demandeur", header: "Demandeur", render: (row) => row.demandeur_label ?? "—" },
    {
      key: "statut",
      header: "Statut",
      render: (row) => <Badge status={REQUEST_STATUS_BADGE[row.statut]}>{REQUEST_STATUS_LABEL[row.statut]}</Badge>,
    },
    {
      key: "actions",
      header: "Actions",
      render: (row) =>
        row.statut === "demandee" && canValidateRequests ? (
          <div className="flex justify-end gap-2">
            <Button
              size="sm"
              onClick={() => handleApproveRequest(row.id)}
              disabled={approveRequest.isPending || rejectRequest.isPending}
            >
              Approuver
            </Button>
            <Button
              size="sm"
              variant="secondary"
              onClick={() => handleRejectRequest(row.id)}
              disabled={approveRequest.isPending || rejectRequest.isPending}
            >
              Rejeter
            </Button>
          </div>
        ) : null,
      align: "right",
    },
  ];

  const orderColumns: DataTableColumn<PurchaseOrder>[] = [
    { key: "supplier", header: "Fournisseur", render: (row) => row.supplier?.nom ?? "—" },
    { key: "site", header: "Site", render: (row) => row.site?.name ?? "—" },
    {
      key: "montant_total",
      header: "Montant total",
      align: "right",
      accessor: (row) => row.montant_total,
      render: (row) => Number(row.montant_total).toLocaleString("fr-FR"),
    },
    { key: "created_by", header: "Créée par", render: (row) => row.created_by_label ?? "—" },
    {
      key: "statut",
      header: "Statut",
      render: (row) => <Badge status={ORDER_STATUS_BADGE[row.statut]}>{ORDER_STATUS_LABEL[row.statut]}</Badge>,
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="font-heading text-xl font-semibold text-text">Achats / Fournisseurs</h1>
          <p className="mt-1 text-sm text-text-muted">
            Demandes d'achat, commandes fournisseurs, chaîne de validation et réceptions.
          </p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Demandes d'achat</CardTitle>
          {canCreate && (
            <Button size="sm" onClick={() => setCreateRequestOpen(true)}>
              <Plus size={14} />
              Nouvelle demande
            </Button>
          )}
        </CardHeader>
        <CardContent className="space-y-3">
          {requestActionError && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
              {requestActionError}
            </p>
          )}
          {requestsQuery.isError ? (
            <ErrorState message={apiErrorMessage(requestsQuery.error)} onRetry={() => requestsQuery.refetch()} />
          ) : (
            <DataTable
              columns={requestColumns}
              data={requestsQuery.data?.data ?? []}
              rowKey={(row) => row.id}
              isLoading={requestsQuery.isLoading}
              emptyState={
                <EmptyState
                  icon={ShoppingCart}
                  title="Aucune demande d'achat"
                  description="Aucune demande d'achat n'a encore été enregistrée."
                  actionLabel={canCreate ? "Nouvelle demande" : undefined}
                  onAction={canCreate ? () => setCreateRequestOpen(true) : undefined}
                />
              }
            />
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Commandes d'achat</CardTitle>
          {canCreate && (
            <Button size="sm" onClick={() => setCreateOrderOpen(true)}>
              <Plus size={14} />
              Nouvelle commande
            </Button>
          )}
        </CardHeader>
        <CardContent className="space-y-4">
          {ordersQuery.isError ? (
            <ErrorState message={apiErrorMessage(ordersQuery.error)} onRetry={() => ordersQuery.refetch()} />
          ) : (
            <DataTable
              columns={orderColumns}
              data={ordersQuery.data?.data ?? []}
              rowKey={(row) => row.id}
              isLoading={ordersQuery.isLoading}
              onRowClick={(row) => setSelectedOrderId(row.id)}
              emptyState={
                <EmptyState
                  icon={Package}
                  title="Aucune commande"
                  description="Aucune commande d'achat n'a encore été créée."
                  actionLabel={canCreate ? "Nouvelle commande" : undefined}
                  onAction={canCreate ? () => setCreateOrderOpen(true) : undefined}
                />
              }
            />
          )}

          {selectedOrderId && (
            <PurchaseOrderDetail orderId={selectedOrderId} onClose={() => setSelectedOrderId(null)} />
          )}
        </CardContent>
      </Card>

      <FournisseursSection />

      <ApprovalRulesSection />

      <CreatePurchaseRequestDialog open={createRequestOpen} onOpenChange={setCreateRequestOpen} />
      <CreatePurchaseOrderDialog open={createOrderOpen} onOpenChange={setCreateOrderOpen} />
    </div>
  );
}

function PurchaseOrderDetail({ orderId, onClose }: { orderId: number; onClose: () => void }) {
  const { hasPermission } = useAuth();
  const orderQuery = usePurchaseOrder(orderId);

  const [actionError, setActionError] = useState<string | null>(null);
  const [receptionItem, setReceptionItem] = useState<PurchaseOrderItem | null>(null);

  const submitOrder = useSubmitPurchaseOrder();
  const approveOrder = useApprovePurchaseOrder();
  const cancelOrder = useCancelPurchaseOrder();

  const canSubmit = hasPermission("achats.update");
  const canApprove = hasPermission("achats.approve");
  const canCancel = hasPermission("achats.cancel");
  const canReceive = hasPermission("stock.create");

  if (orderQuery.isLoading) {
    return (
      <div className="rounded-md border border-border p-4">
        <Skeleton className="h-5 w-48" />
        <Skeleton className="mt-3 h-24 w-full" />
      </div>
    );
  }

  if (orderQuery.isError) {
    return <ErrorState message={apiErrorMessage(orderQuery.error)} onRetry={() => orderQuery.refetch()} />;
  }

  const order = orderQuery.data;
  if (!order) return null;

  function handleSubmit() {
    setActionError(null);
    submitOrder.mutate(orderId, { onError: (err) => setActionError(apiErrorMessage(err)) });
  }

  /**
   * RÈGLE NON NÉGOCIABLE : ce bouton reste visible/cliquable dès que
   * l'utilisateur a la permission achats.approve et que la commande est
   * "en_attente_validation" — jamais désactivé sur la base d'un calcul
   * frontend visant à deviner si le rôle de l'utilisateur "suffit" pour le
   * niveau courant. On clique, on envoie, et on affiche la réponse exacte
   * du backend (403 avec le rôle requis, ou 422 si l'état a changé).
   */
  function handleApprove() {
    setActionError(null);
    approveOrder.mutate(orderId, { onError: (err) => setActionError(apiErrorMessage(err)) });
  }

  function handleCancel() {
    setActionError(null);
    cancelOrder.mutate(orderId, { onError: (err) => setActionError(apiErrorMessage(err)) });
  }

  const alreadyClosed = order.statut === "recue_partielle" || order.statut === "recue_totale" || order.statut === "annulee";

  return (
    <div className="space-y-4 rounded-md border border-border p-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-text">Commande #{order.id}</p>
          <p className="text-xs text-text-muted">
            {order.supplier?.nom ?? "—"} · {order.site?.name ?? "—"}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Badge status={ORDER_STATUS_BADGE[order.statut]}>{ORDER_STATUS_LABEL[order.statut]}</Badge>
          <Button variant="ghost" size="sm" onClick={onClose}>
            Fermer
          </Button>
        </div>
      </div>

      <div className="flex flex-wrap gap-2">
        {order.statut === "brouillon" && canSubmit && (
          <Button size="sm" onClick={handleSubmit} disabled={submitOrder.isPending}>
            {submitOrder.isPending && <LoaderCircle size={14} className="animate-spin" />}
            Soumettre
          </Button>
        )}
        {order.statut === "en_attente_validation" && canApprove && (
          <Button size="sm" onClick={handleApprove} disabled={approveOrder.isPending}>
            {approveOrder.isPending && <LoaderCircle size={14} className="animate-spin" />}
            Valider
          </Button>
        )}
        {!alreadyClosed && canCancel && (
          <Button size="sm" variant="danger" onClick={handleCancel} disabled={cancelOrder.isPending}>
            {cancelOrder.isPending && <LoaderCircle size={14} className="animate-spin" />}
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
        <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Articles</p>
        {order.items.length === 0 ? (
          <p className="text-xs text-text-muted">Aucun article sur cette commande.</p>
        ) : (
          <div className="divide-y divide-border rounded-md border border-border">
            {order.items.map((item) => (
              <div key={item.id} className="flex flex-wrap items-center justify-between gap-3 px-3 py-2 text-sm">
                <div>
                  <p className="text-text">Article #{item.id} · Produit #{item.product_id}</p>
                  <p className="text-xs text-text-muted">
                    Commandé : {item.quantite_commandee} · Reçu : {item.quantite_recue} · Prix unitaire :{" "}
                    {Number(item.prix_unitaire).toLocaleString("fr-FR")}
                  </p>
                </div>
                {canReceive && (
                  <Button size="sm" variant="secondary" onClick={() => setReceptionItem(item)}>
                    Enregistrer réception
                  </Button>
                )}
              </div>
            ))}
          </div>
        )}
      </div>

      <div className="space-y-2">
        <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Chaîne d'approbations</p>
        {order.approvals.length === 0 ? (
          <p className="text-xs text-text-muted">Aucune approbation associée à cette commande.</p>
        ) : (
          <div className="divide-y divide-border rounded-md border border-border">
            {order.approvals.map((approval) => (
              <div key={approval.id} className="flex flex-wrap items-center justify-between gap-3 px-3 py-2 text-sm">
                <div>
                  <p className="text-text">
                    Niveau {approval.level} · Rôle requis « {approval.role_name} » · Seuil{" "}
                    {Number(approval.min_amount).toLocaleString("fr-FR")}
                  </p>
                  <p className="text-xs text-text-muted">
                    {approval.approved_by ? `Approuvé par utilisateur #${approval.approved_by}` : "Non approuvé"}
                    {approval.approved_at ? ` le ${formatDate(approval.approved_at)}` : ""}
                  </p>
                </div>
                <Badge status={APPROVAL_STATUS_BADGE[approval.statut]}>
                  {APPROVAL_STATUS_LABEL[approval.statut]}
                </Badge>
              </div>
            ))}
          </div>
        )}
      </div>

      {receptionItem && (
        <RecordReceptionDialog
          open={Boolean(receptionItem)}
          onOpenChange={(open) => !open && setReceptionItem(null)}
          item={receptionItem}
          productLabel={`Produit #${receptionItem.product_id}`}
        />
      )}
    </div>
  );
}
