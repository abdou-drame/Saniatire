import { FlaskConical } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Skeleton, TableSkeleton } from "@/components/ui/loading-state";
import { roleLabel } from "@/config/role-labels";
import { useAuth } from "@/hooks/use-auth";
import { useLabOrder, useLabOrders, useTransmitLabResult, useValidateLabResultBiologique } from "@/hooks/use-lab-orders";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDateTime } from "@/lib/datetime";
import { ORDER_STATUS_BADGE, ORDER_STATUS_LABEL, RESULT_STATUS_BADGE, RESULT_STATUS_LABEL } from "@/pages/laboratoire/lab-status";
import type { LabOrderItem, LabResult } from "@/types/api";

export function LaboratoireBiologisteSection() {
  const ordersQuery = useLabOrders({});
  const [selectedId, setSelectedId] = useState<number | null>(null);

  // L'ordre ne passe à "resultats_disponibles" qu'une fois qu'au moins un
  // résultat est déjà validé biologiquement (LabOrder::syncStatusFromChildren) :
  // pendant qu'un résultat est en attente de validation biologique, la commande
  // est encore au statut "en_analyse". On inclut donc les deux statuts ici ; le
  // filtre réel qui décide si une action est proposée reste au niveau du
  // résultat (result.status), pas de ce filtre d'affichage.
  const pendingOrders = (ordersQuery.data?.data ?? []).filter((order) =>
    ["en_analyse", "resultats_disponibles"].includes(order.status),
  );

  return (
    <Card>
      <CardHeader>
        <CardTitle>Poste biologiste</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {ordersQuery.isError ? (
          <ErrorState message={apiErrorMessage(ordersQuery.error)} onRetry={() => ordersQuery.refetch()} />
        ) : ordersQuery.isLoading ? (
          <TableSkeleton rows={3} columns={4} />
        ) : pendingOrders.length === 0 ? (
          <EmptyState
            icon={FlaskConical}
            title="Aucun résultat à valider"
            description="Aucune commande n'a de résultats en attente de validation biologique."
          />
        ) : (
          <div className="divide-y divide-border rounded-md border border-border">
            {pendingOrders.map((order) => (
              <button
                key={order.id}
                type="button"
                onClick={() => setSelectedId(order.id)}
                className={`flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm hover:bg-surface-hover ${
                  selectedId === order.id ? "bg-surface-hover" : ""
                }`}
              >
                <div>
                  <p className="font-medium text-text">
                    #{order.id} —{" "}
                    {order.patient ? `${order.patient.first_name} ${order.patient.last_name}` : "—"}
                  </p>
                  <p className="text-xs text-text-muted">
                    {order.requester_label ?? "—"} · {formatDateTime(order.ordered_at)}
                  </p>
                </div>
                <Badge status={ORDER_STATUS_BADGE[order.status]}>{ORDER_STATUS_LABEL[order.status]}</Badge>
              </button>
            ))}
          </div>
        )}

        {selectedId && <BiologisteOrderDetail orderId={selectedId} onClose={() => setSelectedId(null)} />}
      </CardContent>
    </Card>
  );
}

function BiologisteOrderDetail({ orderId, onClose }: { orderId: number; onClose: () => void }) {
  const { hasPermission } = useAuth();
  const orderQuery = useLabOrder(orderId);

  if (orderQuery.isLoading) {
    return (
      <div className="rounded-md border border-border p-4">
        <Skeleton className="h-5 w-48" />
        <Skeleton className="mt-3 h-20 w-full" />
      </div>
    );
  }

  if (orderQuery.isError) {
    return <ErrorState message={apiErrorMessage(orderQuery.error)} onRetry={() => orderQuery.refetch()} />;
  }

  const order = orderQuery.data;
  if (!order) return null;

  const canValidate = hasPermission("laboratoire.validate_biologique");
  const resultItems = order.items.filter((item) => item.result !== null);

  return (
    <div className="space-y-4 rounded-md border border-border p-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-text">Commande #{order.id}</p>
          <p className="text-xs text-text-muted">
            {order.patient
              ? `${order.patient.first_name} ${order.patient.last_name} (${order.patient.patient_number})`
              : "—"}{" "}
            · {order.site?.name ?? "—"}
          </p>
        </div>
        <Button variant="ghost" size="sm" onClick={onClose}>
          Fermer
        </Button>
      </div>

      {resultItems.length === 0 ? (
        <p className="text-xs text-text-subtle">Aucun résultat saisi pour cette commande.</p>
      ) : (
        <div className="space-y-3">
          {resultItems.map((item) => (
            <BiologisteResultRow key={item.id} item={item} canValidate={canValidate} />
          ))}
        </div>
      )}
    </div>
  );
}

function isOutOfRange(result: LabResult): boolean {
  if (result.reference_min === null || result.reference_max === null) return false;
  const numeric = Number(result.value);
  if (Number.isNaN(numeric)) return false;
  return numeric < result.reference_min || numeric > result.reference_max;
}

function BiologisteResultRow({ item, canValidate }: { item: LabOrderItem; canValidate: boolean }) {
  const result = item.result;
  const [confirmAction, setConfirmAction] = useState<"validate" | "transmit" | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const validateBiologique = useValidateLabResultBiologique();
  const transmit = useTransmitLabResult();

  if (!result) return null;

  const hasReferenceRange = result.reference_min !== null && result.reference_max !== null;
  const isCritical = result.interpretation === "critique" || (hasReferenceRange && isOutOfRange(result));
  const isPending = validateBiologique.isPending || transmit.isPending;

  function handleConfirm() {
    setActionError(null);
    if (confirmAction === "validate") {
      validateBiologique.mutate(
        { id: result!.id },
        { onSuccess: () => setConfirmAction(null), onError: (err) => setActionError(apiErrorMessage(err)) },
      );
    } else if (confirmAction === "transmit") {
      transmit.mutate(
        { id: result!.id },
        { onSuccess: () => setConfirmAction(null), onError: (err) => setActionError(apiErrorMessage(err)) },
      );
    }
  }

  return (
    <div className="rounded-md border border-border p-3">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-sm text-text">{item.loinc_code?.label ?? "Analyse"}</p>
          <p className="text-xs text-text-subtle">
            {result.value} {result.unit ?? ""}
            {hasReferenceRange && ` (référence : ${result.reference_min}–${result.reference_max})`}
          </p>
        </div>
        <div className="flex flex-wrap items-center justify-end gap-2">
          {hasReferenceRange && (
            <Badge status={isCritical ? "danger" : "success"}>
              {isCritical ? (result.interpretation === "critique" ? "Critique" : "Hors normes") : "Normal"}
            </Badge>
          )}
          <Badge status={RESULT_STATUS_BADGE[result.status]}>{RESULT_STATUS_LABEL[result.status]}</Badge>
        </div>
      </div>

      {result.technical_validated_at && (
        <p className="mt-1 text-xs text-text-subtle">
          Validé techniquement le {formatDateTime(result.technical_validated_at)}
          {result.technical_validator_label && (
            <>
              {" "}
              par {result.technical_validator_label}
              {result.technical_validator_role ? ` (${roleLabel(result.technical_validator_role)})` : ""}
            </>
          )}
        </p>
      )}
      {result.biological_validated_at && (
        <p className="mt-1 text-xs text-text-subtle">
          Validé biologiquement le {formatDateTime(result.biological_validated_at)}
          {result.biological_validator_label && (
            <>
              {" "}
              par {result.biological_validator_label}
              {result.biological_validator_role ? ` (${roleLabel(result.biological_validator_role)})` : ""}
            </>
          )}
        </p>
      )}

      {actionError && (
        <p className="mt-2 rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
          {actionError}
        </p>
      )}

      {result.status === "validation_biologique_attente" && canValidate && (
        <Button size="sm" className="mt-2" onClick={() => setConfirmAction("validate")}>
          Valider (biologique)
        </Button>
      )}
      {result.status === "valide" && canValidate && (
        <Button size="sm" className="mt-2" onClick={() => setConfirmAction("transmit")}>
          Transmettre
        </Button>
      )}

      <ConfirmDialog
        open={confirmAction !== null}
        onOpenChange={(open) => !open && setConfirmAction(null)}
        title={confirmAction === "transmit" ? "Transmission du résultat" : "Validation biologique"}
        description={
          confirmAction === "transmit"
            ? `Confirmer la transmission du résultat de « ${item.loinc_code?.label ?? "cette analyse"} ».`
            : `Confirmer la validation biologique du résultat de « ${item.loinc_code?.label ?? "cette analyse"} » (valeur : ${result.value} ${result.unit ?? ""}).`
        }
        isDestructive={false}
        isPending={isPending}
        onConfirm={handleConfirm}
      />
    </div>
  );
}
