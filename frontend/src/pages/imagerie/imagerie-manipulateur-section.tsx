import { LoaderCircle, Scan } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton, TableSkeleton } from "@/components/ui/loading-state";
import { useAuth } from "@/hooks/use-auth";
import { useCreateImagingStudy, useImagingOrder, useImagingOrders } from "@/hooks/use-imaging-orders";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDateTime } from "@/lib/datetime";
import {
  EXAM_TYPE_LABEL,
  ORDER_STATUS_BADGE,
  ORDER_STATUS_LABEL,
  STUDY_STATUS_BADGE,
  STUDY_STATUS_LABEL,
} from "@/pages/imagerie/imaging-status";
import type { ImagingOrder } from "@/types/api";

const ACTIVE_STATUSES = ["demande"] as const;

function toDatetimeLocalValue(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function ImagerieManipulateurSection() {
  const ordersQuery = useImagingOrders({});
  const [selectedId, setSelectedId] = useState<number | null>(null);

  const activeOrders = (ordersQuery.data?.data ?? []).filter((order) =>
    (ACTIVE_STATUSES as readonly string[]).includes(order.status),
  );

  return (
    <Card>
      <CardHeader>
        <CardTitle>Poste manipulateur radio</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {ordersQuery.isError ? (
          <ErrorState message={apiErrorMessage(ordersQuery.error)} onRetry={() => ordersQuery.refetch()} />
        ) : ordersQuery.isLoading ? (
          <TableSkeleton rows={3} columns={4} />
        ) : activeOrders.length === 0 ? (
          <EmptyState
            icon={Scan}
            title="Aucun examen à réaliser"
            description="Aucune demande n'est en attente de réalisation."
          />
        ) : (
          <div className="divide-y divide-border rounded-md border border-border">
            {activeOrders.map((order) => (
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

        {selectedId && <ManipulateurOrderDetail orderId={selectedId} onClose={() => setSelectedId(null)} />}
      </CardContent>
    </Card>
  );
}

function ManipulateurOrderDetail({ orderId, onClose }: { orderId: number; onClose: () => void }) {
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

  const canCreate = hasPermission("imagerie.create");

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

      {order.notes && <p className="text-xs text-text-muted">Motif : {order.notes}</p>}

      {order.studies.length === 0 && canCreate && <CreateStudyForm order={order} />}

      {order.studies.length > 0 && (
        <div className="space-y-2">
          <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Examens réalisés</p>
          {order.studies.map((study) => (
            <div
              key={study.id}
              className="flex items-center justify-between gap-3 rounded-md border border-border bg-surface px-3 py-2 text-xs text-text-muted"
            >
              <span>
                {study.modality} · réalisé le {formatDateTime(study.performed_at)}
              </span>
              <Badge status={STUDY_STATUS_BADGE[study.status]}>{STUDY_STATUS_LABEL[study.status]}</Badge>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function CreateStudyForm({ order }: { order: ImagingOrder }) {
  const [modality, setModality] = useState("");
  const [performedAt, setPerformedAt] = useState(() => toDatetimeLocalValue(new Date()));
  const suggestedSuffix = Date.now().toString().slice(-8);
  const [studyInstanceUid, setStudyInstanceUid] = useState(() => `IMG-${order.id}-${suggestedSuffix}`);
  const [accessionNumber, setAccessionNumber] = useState(() => `ACC-${order.id}-${suggestedSuffix}`);
  const [storageReference, setStorageReference] = useState("");
  const [externalReferenceUrl, setExternalReferenceUrl] = useState("");
  const [error, setError] = useState<string | null>(null);
  const createStudy = useCreateImagingStudy();

  function handleSubmit() {
    setError(null);
    createStudy.mutate(
      {
        imagingOrderId: order.id,
        study_instance_uid: studyInstanceUid,
        accession_number: accessionNumber,
        modality,
        performed_at: performedAt ? new Date(performedAt).toISOString() : undefined,
        storage_reference: storageReference.trim() || undefined,
        external_reference_url: externalReferenceUrl.trim() || undefined,
      },
      { onError: (err) => setError(apiErrorMessage(err)) },
    );
  }

  return (
    <div className="space-y-3 rounded-md border border-dashed border-border p-3">
      <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Enregistrer la réalisation</p>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
          <Label>Modalité</Label>
          <Input value={modality} onChange={(e) => setModality(e.target.value)} placeholder="ex. RX thorax face" />
        </div>
        <div>
          <Label>Date/heure de réalisation</Label>
          <Input type="datetime-local" value={performedAt} onChange={(e) => setPerformedAt(e.target.value)} />
        </div>
        <div>
          <Label>UID d'étude</Label>
          <Input value={studyInstanceUid} onChange={(e) => setStudyInstanceUid(e.target.value)} />
        </div>
        <div>
          <Label>Numéro d'accession</Label>
          <Input value={accessionNumber} onChange={(e) => setAccessionNumber(e.target.value)} />
        </div>
        <div className="sm:col-span-2">
          <Label>Référence de stockage (aucun PACS réel — champ texte)</Label>
          <Input
            value={storageReference}
            onChange={(e) => setStorageReference(e.target.value)}
            placeholder="ex. archive locale, dossier partagé..."
          />
        </div>
        <div className="sm:col-span-2">
          <Label>Lien / référence externe</Label>
          <Input
            value={externalReferenceUrl}
            onChange={(e) => setExternalReferenceUrl(e.target.value)}
            placeholder="ex. lien vers le visualisateur externe"
          />
        </div>
      </div>
      {error && (
        <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{error}</p>
      )}
      <Button
        size="sm"
        onClick={handleSubmit}
        disabled={!modality.trim() || !studyInstanceUid.trim() || !accessionNumber.trim() || createStudy.isPending}
      >
        {createStudy.isPending && <LoaderCircle size={14} className="animate-spin" />}
        Enregistrer
      </Button>
    </div>
  );
}
