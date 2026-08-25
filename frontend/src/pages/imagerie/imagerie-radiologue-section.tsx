import { LoaderCircle, Scan } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Skeleton, TableSkeleton } from "@/components/ui/loading-state";
import { useAuth } from "@/hooks/use-auth";
import {
  useCreateImagingReport,
  useImagingOrder,
  useImagingOrders,
  useTransmitImagingStudy,
  useValidateImagingReport,
} from "@/hooks/use-imaging-orders";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDateTime } from "@/lib/datetime";
import {
  EXAM_TYPE_LABEL,
  ORDER_STATUS_BADGE,
  ORDER_STATUS_LABEL,
  REPORT_STATUS_BADGE,
  REPORT_STATUS_LABEL,
  STUDY_STATUS_BADGE,
  STUDY_STATUS_LABEL,
} from "@/pages/imagerie/imaging-status";
import type { ImagingStudy } from "@/types/api";

const PENDING_STATUSES = ["realise", "en_interpretation", "cr_redige", "valide"] as const;

export function ImagerieRadiologueSection() {
  const ordersQuery = useImagingOrders({});
  const [selectedId, setSelectedId] = useState<number | null>(null);

  const pendingOrders = (ordersQuery.data?.data ?? []).filter((order) =>
    (PENDING_STATUSES as readonly string[]).includes(order.status),
  );

  return (
    <Card>
      <CardHeader>
        <CardTitle>Poste radiologue</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {ordersQuery.isError ? (
          <ErrorState message={apiErrorMessage(ordersQuery.error)} onRetry={() => ordersQuery.refetch()} />
        ) : ordersQuery.isLoading ? (
          <TableSkeleton rows={3} columns={4} />
        ) : pendingOrders.length === 0 ? (
          <EmptyState
            icon={Scan}
            title="Aucun examen à interpréter"
            description="Aucun examen réalisé n'est en attente de compte rendu, de validation ou de transmission."
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
                    #{order.id} — {order.patient ? `${order.patient.first_name} ${order.patient.last_name}` : "—"}
                  </p>
                  <p className="text-xs text-text-muted">
                    {EXAM_TYPE_LABEL[order.exam_type]} · {order.requester_label ?? "—"} ·{" "}
                    {formatDateTime(order.ordered_at)}
                  </p>
                </div>
                <Badge status={ORDER_STATUS_BADGE[order.status]}>{ORDER_STATUS_LABEL[order.status]}</Badge>
              </button>
            ))}
          </div>
        )}

        {selectedId && <RadiologueOrderDetail orderId={selectedId} onClose={() => setSelectedId(null)} />}
      </CardContent>
    </Card>
  );
}

function RadiologueOrderDetail({ orderId, onClose }: { orderId: number; onClose: () => void }) {
  const { hasPermission } = useAuth();
  const orderQuery = useImagingOrder(orderId);

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

  const canInterpret = hasPermission("imagerie.interpreter");
  const canValidate = hasPermission("imagerie.validate");

  return (
    <div className="space-y-4 rounded-md border border-border p-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-text">Commande #{order.id}</p>
          <p className="text-xs text-text-muted">
            {order.patient
              ? `${order.patient.first_name} ${order.patient.last_name} (${order.patient.patient_number})`
              : "—"}{" "}
            · {EXAM_TYPE_LABEL[order.exam_type]} · {order.site?.name ?? "—"}
          </p>
        </div>
        <Button variant="ghost" size="sm" onClick={onClose}>
          Fermer
        </Button>
      </div>

      {order.studies.length === 0 ? (
        <p className="text-xs text-text-subtle">Aucun examen réalisé pour cette commande.</p>
      ) : (
        <div className="space-y-3">
          {order.studies.map((study) => (
            <StudyRow key={study.id} study={study} canInterpret={canInterpret} canValidate={canValidate} />
          ))}
        </div>
      )}
    </div>
  );
}

function StudyRow({
  study,
  canInterpret,
  canValidate,
}: {
  study: ImagingStudy;
  canInterpret: boolean;
  canValidate: boolean;
}) {
  const [content, setContent] = useState("");
  const [reportError, setReportError] = useState<string | null>(null);
  const [confirmAction, setConfirmAction] = useState<"validate" | "transmit" | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  const createReport = useCreateImagingReport();
  const validateReport = useValidateImagingReport();
  const transmit = useTransmitImagingStudy();

  const report = study.report;
  const isPending = validateReport.isPending || transmit.isPending;

  function handleCreateReport() {
    setReportError(null);
    createReport.mutate(
      { imagingStudyId: study.id, content },
      { onError: (err) => setReportError(apiErrorMessage(err)) },
    );
  }

  function handleConfirm() {
    if (!report) return;
    setActionError(null);
    if (confirmAction === "validate") {
      validateReport.mutate(
        { id: report.id },
        { onSuccess: () => setConfirmAction(null), onError: (err) => setActionError(apiErrorMessage(err)) },
      );
    } else if (confirmAction === "transmit") {
      transmit.mutate(
        { id: study.id },
        { onSuccess: () => setConfirmAction(null), onError: (err) => setActionError(apiErrorMessage(err)) },
      );
    }
  }

  return (
    <div className="rounded-md border border-border p-3">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-sm text-text">{study.modality}</p>
          <p className="text-xs text-text-subtle">réalisé le {formatDateTime(study.performed_at)}</p>
        </div>
        <div className="flex flex-wrap items-center justify-end gap-2">
          {report && <Badge status={REPORT_STATUS_BADGE[report.status]}>{REPORT_STATUS_LABEL[report.status]}</Badge>}
          <Badge status={STUDY_STATUS_BADGE[study.status]}>{STUDY_STATUS_LABEL[study.status]}</Badge>
        </div>
      </div>

      {(study.storage_reference || study.external_reference_url) && (
        <p className="mt-1 text-xs text-text-subtle">
          Référence : {study.storage_reference ?? study.external_reference_url}
        </p>
      )}

      {!report && canInterpret && (
        <div className="mt-2 space-y-2">
          <textarea
            value={content}
            onChange={(e) => setContent(e.target.value)}
            rows={4}
            className="w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
            placeholder="Rédiger le compte rendu..."
          />
          {reportError && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
              {reportError}
            </p>
          )}
          <Button size="sm" onClick={handleCreateReport} disabled={!content.trim() || createReport.isPending}>
            {createReport.isPending && <LoaderCircle size={14} className="animate-spin" />}
            Rédiger le compte rendu
          </Button>
        </div>
      )}

      {report && (
        <div className="mt-2 space-y-2">
          <p className="whitespace-pre-wrap rounded-md border border-border bg-surface px-3 py-2 text-xs text-text-muted">
            {report.content}
          </p>

          {actionError && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
              {actionError}
            </p>
          )}

          {report.status === "brouillon" && canValidate && (
            <Button size="sm" onClick={() => setConfirmAction("validate")}>
              Valider
            </Button>
          )}
          {report.status === "valide" && study.status !== "transmis" && canValidate && (
            <Button size="sm" onClick={() => setConfirmAction("transmit")}>
              Transmettre
            </Button>
          )}
        </div>
      )}

      <ConfirmDialog
        open={confirmAction !== null}
        onOpenChange={(open) => !open && setConfirmAction(null)}
        title={confirmAction === "transmit" ? "Transmission de l'examen" : "Validation du compte rendu"}
        description={
          confirmAction === "transmit"
            ? `Confirmer la transmission de l'examen « ${study.modality} ».`
            : `Confirmer la validation du compte rendu de l'examen « ${study.modality} ».`
        }
        isDestructive={false}
        isPending={isPending}
        onConfirm={handleConfirm}
      />
    </div>
  );
}
