import { LoaderCircle, TestTube2 } from "lucide-react";
import { useEffect, useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton, TableSkeleton } from "@/components/ui/loading-state";
import { Select } from "@/components/ui/select";
import { useAuth } from "@/hooks/use-auth";
import {
  useLabOrder,
  useLabOrders,
  useRegisterLabResult,
  useRegisterLabSample,
  useValidateLabResultTechnique,
} from "@/hooks/use-lab-orders";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDateTime } from "@/lib/datetime";
import { ORDER_STATUS_BADGE, ORDER_STATUS_LABEL, RESULT_STATUS_BADGE, RESULT_STATUS_LABEL } from "@/pages/laboratoire/lab-status";
import type { LabOrder, LabOrderItem } from "@/types/api";

/**
 * L'ordre de la commande n'atteint "resultats_disponibles" qu'une fois qu'au
 * moins un résultat est déjà validé biologiquement (cf. LabOrder::syncStatusFromChildren)
 * — une commande à plusieurs analyses peut donc avoir un item encore en attente
 * de validation technique alors que son statut global est déjà "resultats_disponibles".
 * On l'inclut ici pour ne pas masquer ce cas ; le bouton d'action réel reste
 * gardé au niveau de l'item par son propre statut (result.status), pas par
 * ce filtre d'affichage.
 */
const ACTIVE_STATUSES = ["demande", "prelevement_effectue", "en_analyse", "resultats_disponibles"] as const;

function toDatetimeLocalValue(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function LaboratoireTechnicienSection() {
  const ordersQuery = useLabOrders({});
  const [selectedId, setSelectedId] = useState<number | null>(null);

  const activeOrders = (ordersQuery.data?.data ?? []).filter((order) =>
    (ACTIVE_STATUSES as readonly string[]).includes(order.status),
  );

  return (
    <Card>
      <CardHeader>
        <CardTitle>Poste technicien</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {ordersQuery.isError ? (
          <ErrorState message={apiErrorMessage(ordersQuery.error)} onRetry={() => ordersQuery.refetch()} />
        ) : ordersQuery.isLoading ? (
          <TableSkeleton rows={3} columns={4} />
        ) : activeOrders.length === 0 ? (
          <EmptyState
            icon={TestTube2}
            title="Aucune commande active"
            description="Aucune demande n'est en attente de prélèvement ou d'analyse."
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

        {selectedId && <TechnicienOrderDetail orderId={selectedId} onClose={() => setSelectedId(null)} />}
      </CardContent>
    </Card>
  );
}

function TechnicienOrderDetail({ orderId, onClose }: { orderId: number; onClose: () => void }) {
  const { user, hasPermission } = useAuth();
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

  const canCreate = hasPermission("laboratoire.create");
  const canValidateTechnique = hasPermission("laboratoire.validate_technique");
  const practitionerName = user ? `${user.first_name} ${user.last_name}` : "—";

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

      {order.notes && <p className="text-xs text-text-muted">Motif : {order.notes}</p>}

      {order.samples.length === 0 && canCreate && (
        <RegisterSampleForm order={order} practitionerName={practitionerName} />
      )}

      {order.samples.length > 0 && (
        <div className="space-y-2">
          <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Prélèvements</p>
          {order.samples.map((sample) => (
            <div
              key={sample.id}
              className="rounded-md border border-border bg-surface px-3 py-2 text-xs text-text-muted"
            >
              {sample.barcode} · {sample.sample_type} · prélevé le {formatDateTime(sample.collected_at)}
            </div>
          ))}
        </div>
      )}

      {order.items.length > 0 && (
        <div className="space-y-3">
          <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Analyses</p>
          {order.items.map((item) => (
            <LabOrderItemRow
              key={item.id}
              order={order}
              item={item}
              canCreate={canCreate}
              canValidateTechnique={canValidateTechnique}
            />
          ))}
        </div>
      )}
    </div>
  );
}

function RegisterSampleForm({ order, practitionerName }: { order: LabOrder; practitionerName: string }) {
  const [sampleType, setSampleType] = useState("");
  const [collectedAt, setCollectedAt] = useState(() => toDatetimeLocalValue(new Date()));
  const [barcode, setBarcode] = useState(() => `LB-${order.id}-${Date.now().toString().slice(-6)}`);
  const [error, setError] = useState<string | null>(null);
  const registerSample = useRegisterLabSample();

  function handleSubmit() {
    setError(null);
    registerSample.mutate(
      {
        labOrderId: order.id,
        barcode,
        sample_type: sampleType,
        collected_at: collectedAt ? new Date(collectedAt).toISOString() : undefined,
      },
      { onError: (err) => setError(apiErrorMessage(err)) },
    );
  }

  return (
    <div className="space-y-3 rounded-md border border-dashed border-border p-3">
      <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Enregistrer un prélèvement</p>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
          <Label>Type de prélèvement</Label>
          <Input value={sampleType} onChange={(e) => setSampleType(e.target.value)} placeholder="ex. sang veineux" />
        </div>
        <div>
          <Label>Date/heure de prélèvement</Label>
          <Input type="datetime-local" value={collectedAt} onChange={(e) => setCollectedAt(e.target.value)} />
        </div>
        <div className="sm:col-span-2">
          <Label>Code-barres</Label>
          <Input value={barcode} onChange={(e) => setBarcode(e.target.value)} />
        </div>
      </div>
      <p className="text-xs text-text-subtle">Agent préleveur : {practitionerName}</p>
      {error && (
        <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{error}</p>
      )}
      <Button
        size="sm"
        onClick={handleSubmit}
        disabled={!sampleType.trim() || !barcode.trim() || registerSample.isPending}
      >
        {registerSample.isPending && <LoaderCircle size={14} className="animate-spin" />}
        Enregistrer
      </Button>
    </div>
  );
}

function LabOrderItemRow({
  order,
  item,
  canCreate,
  canValidateTechnique,
}: {
  order: LabOrder;
  item: LabOrderItem;
  canCreate: boolean;
  canValidateTechnique: boolean;
}) {
  const [showResultForm, setShowResultForm] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [validateError, setValidateError] = useState<string | null>(null);
  const validateTechnique = useValidateLabResultTechnique();
  const result = item.result;

  return (
    <div className="rounded-md border border-border p-3">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-sm text-text">{item.loinc_code?.label ?? "Analyse"}</p>
          <p className="text-xs text-text-subtle">{item.loinc_code?.code}</p>
        </div>
        {result && <Badge status={RESULT_STATUS_BADGE[result.status]}>{RESULT_STATUS_LABEL[result.status]}</Badge>}
      </div>

      {result && (
        <p className="mt-1 text-xs text-text-muted">
          Valeur : {result.value} {result.unit ?? ""}
        </p>
      )}

      {!result && order.samples.length > 0 && canCreate && !showResultForm && (
        <Button variant="secondary" size="sm" className="mt-2" onClick={() => setShowResultForm(true)}>
          Saisir un résultat
        </Button>
      )}

      {!result && showResultForm && (
        <RegisterResultForm order={order} item={item} onClose={() => setShowResultForm(false)} />
      )}

      {result && result.status === "validation_technique_attente" && canValidateTechnique && (
        <>
          <Button size="sm" className="mt-2" onClick={() => setConfirmOpen(true)}>
            Validation technique
          </Button>
          {validateError && (
            <p className="mt-2 rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
              {validateError}
            </p>
          )}
          <ConfirmDialog
            open={confirmOpen}
            onOpenChange={setConfirmOpen}
            title="Validation technique"
            description={`Confirmer la validation technique du résultat de « ${item.loinc_code?.label ?? "cette analyse"} » (valeur saisie : ${result.value} ${result.unit ?? ""}).`}
            isDestructive={false}
            isPending={validateTechnique.isPending}
            onConfirm={() => {
              setValidateError(null);
              validateTechnique.mutate(
                { id: result.id },
                {
                  onSuccess: () => setConfirmOpen(false),
                  onError: (err) => setValidateError(apiErrorMessage(err)),
                },
              );
            }}
          />
        </>
      )}
    </div>
  );
}

function RegisterResultForm({
  order,
  item,
  onClose,
}: {
  order: LabOrder;
  item: LabOrderItem;
  onClose: () => void;
}) {
  const [sampleId, setSampleId] = useState<number | null>(order.samples[0]?.id ?? null);
  const [value, setValue] = useState("");
  const [unit, setUnit] = useState("");
  const [referenceMin, setReferenceMin] = useState("");
  const [referenceMax, setReferenceMax] = useState("");
  const [interpretation, setInterpretation] = useState<"" | "normal" | "anormal" | "critique">("");
  const [error, setError] = useState<string | null>(null);
  const registerResult = useRegisterLabResult();

  useEffect(() => {
    setSampleId((current) => current ?? order.samples[0]?.id ?? null);
  }, [order.samples]);

  function handleSubmit() {
    if (!sampleId) return;
    setError(null);
    registerResult.mutate(
      {
        labSampleId: sampleId,
        lab_order_item_id: item.id,
        value,
        unit: unit.trim() || undefined,
        reference_min: referenceMin.trim() ? Number(referenceMin) : undefined,
        reference_max: referenceMax.trim() ? Number(referenceMax) : undefined,
        interpretation: interpretation || undefined,
      },
      {
        onSuccess: () => onClose(),
        onError: (err) => setError(apiErrorMessage(err)),
      },
    );
  }

  return (
    <div className="mt-2 space-y-3 rounded-md border border-dashed border-border p-3">
      {order.samples.length > 1 && (
        <div>
          <Label>Prélèvement</Label>
          <Select
            value={sampleId ?? ""}
            onChange={(e) => setSampleId(e.target.value ? Number(e.target.value) : null)}
          >
            {order.samples.map((sample) => (
              <option key={sample.id} value={sample.id}>
                {sample.barcode} — {sample.sample_type}
              </option>
            ))}
          </Select>
        </div>
      )}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
          <Label>Valeur</Label>
          <Input value={value} onChange={(e) => setValue(e.target.value)} />
        </div>
        <div>
          <Label>Unité</Label>
          <Input value={unit} onChange={(e) => setUnit(e.target.value)} />
        </div>
        <div>
          <Label>Référence min</Label>
          <Input type="number" value={referenceMin} onChange={(e) => setReferenceMin(e.target.value)} />
        </div>
        <div>
          <Label>Référence max</Label>
          <Input type="number" value={referenceMax} onChange={(e) => setReferenceMax(e.target.value)} />
        </div>
        <div className="sm:col-span-2">
          <Label>Interprétation</Label>
          <Select
            value={interpretation}
            onChange={(e) => setInterpretation(e.target.value as typeof interpretation)}
          >
            <option value="">—</option>
            <option value="normal">Normal</option>
            <option value="anormal">Anormal</option>
            <option value="critique">Critique</option>
          </Select>
        </div>
      </div>
      {error && (
        <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{error}</p>
      )}
      <div className="flex gap-2">
        <Button size="sm" onClick={handleSubmit} disabled={!sampleId || !value.trim() || registerResult.isPending}>
          {registerResult.isPending && <LoaderCircle size={14} className="animate-spin" />}
          Enregistrer
        </Button>
        <Button size="sm" variant="secondary" onClick={onClose}>
          Annuler
        </Button>
      </div>
    </div>
  );
}
