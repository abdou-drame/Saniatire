import { useMemo, useState } from "react";
import { ChevronDown, ClipboardList, FlaskConical, Scan } from "lucide-react";
import { Link } from "react-router-dom";
import { Badge, type BadgeProps } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { TableSkeleton } from "@/components/ui/loading-state";
import { usePrescriberImagingOrders, usePrescriberLabOrders } from "@/hooks/portal-prescripteur/use-prescriber-requests";
import { formatDate } from "@/lib/datetime";
import { portalErrorMessage } from "@/lib/portal-error";
import type {
  PrescriberImagingOrder,
  PrescriberImagingOrderStatus,
  PrescriberLabOrder,
  PrescriberLabOrderStatus,
} from "@/types/api";

type Tracking = "en_cours" | "resultat_disponible" | "transmis" | "annule";

const LAB_TRACKING: Record<PrescriberLabOrderStatus, Tracking> = {
  demande: "en_cours",
  prelevement_effectue: "en_cours",
  en_analyse: "en_cours",
  resultats_disponibles: "resultat_disponible",
  transmis: "transmis",
  annule: "annule",
};

const IMAGING_TRACKING: Record<PrescriberImagingOrderStatus, Tracking> = {
  demande: "en_cours",
  planifie: "en_cours",
  realise: "en_cours",
  en_interpretation: "en_cours",
  cr_redige: "en_cours",
  valide: "resultat_disponible",
  transmis: "transmis",
  annule: "annule",
};

const TRACKING_META: Record<Tracking, { label: string; status: BadgeProps["status"] }> = {
  en_cours: { label: "En cours", status: "warning" },
  resultat_disponible: { label: "Résultat disponible", status: "accent" },
  transmis: { label: "Transmis", status: "success" },
  annule: { label: "Annulée", status: "neutral" },
};

const EXAM_TYPE_LABEL: Record<PrescriberImagingOrder["exam_type"], string> = {
  radio: "Radiographie",
  echo: "Échographie",
  scanner: "Scanner",
  irm: "IRM",
};

interface Row {
  key: string;
  type: "labo" | "imagerie";
  order: PrescriberLabOrder | PrescriberImagingOrder;
  tracking: Tracking;
}

export function PrescriberRequestsPage() {
  const laboQuery = usePrescriberLabOrders();
  const imagerieQuery = usePrescriberImagingOrders();
  const [expanded, setExpanded] = useState<string | null>(null);

  const isError = laboQuery.isError || imagerieQuery.isError;
  const isLoading = laboQuery.isLoading || imagerieQuery.isLoading;

  const rows = useMemo<Row[]>(() => {
    const labo: Row[] = (laboQuery.data ?? []).map((order) => ({
      key: `labo-${order.id}`,
      type: "labo",
      order,
      tracking: LAB_TRACKING[order.status],
    }));
    const imagerie: Row[] = (imagerieQuery.data ?? []).map((order) => ({
      key: `imagerie-${order.id}`,
      type: "imagerie",
      order,
      tracking: IMAGING_TRACKING[order.status],
    }));
    return [...labo, ...imagerie].sort((a, b) => {
      const dateA = a.order.ordered_at ? new Date(a.order.ordered_at).getTime() : 0;
      const dateB = b.order.ordered_at ? new Date(b.order.ordered_at).getTime() : 0;
      return dateB - dateA;
    });
  }, [laboQuery.data, imagerieQuery.data]);

  function retry() {
    laboQuery.refetch();
    imagerieQuery.refetch();
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-3">
        <h2 className="font-heading text-lg font-semibold text-text">Mes demandes</h2>
        <Button asChild size="sm">
          <Link to="/portail-prescripteur/demandes/nouvelle">Nouvelle demande</Link>
        </Button>
      </div>

      {isError ? (
        <ErrorState
          message={portalErrorMessage(laboQuery.error ?? imagerieQuery.error)}
          onRetry={retry}
        />
      ) : isLoading ? (
        <TableSkeleton rows={4} columns={3} />
      ) : rows.length === 0 ? (
        <EmptyState
          icon={ClipboardList}
          title="Aucune demande"
          description="Vos demandes d'examens envoyées apparaîtront ici."
          actionLabel="Créer une demande"
          onAction={() => {
            window.location.href = "/portail-prescripteur/demandes/nouvelle";
          }}
        />
      ) : (
        <Card>
          <CardContent className="space-y-2 pt-5">
            {rows.map((row) => {
              const meta = TRACKING_META[row.tracking];
              const isOpen = expanded === row.key;
              const patient = row.order.patient;
              return (
                <div key={row.key} className="rounded-md border border-border bg-surface">
                  <button
                    type="button"
                    onClick={() => setExpanded(isOpen ? null : row.key)}
                    className="flex w-full items-center justify-between gap-4 px-4 py-3 text-left transition-colors hover:bg-surface-hover"
                  >
                    <div className="flex min-w-0 items-center gap-3">
                      <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-accent/10 text-accent-light">
                        {row.type === "labo" ? <FlaskConical size={15} /> : <Scan size={15} />}
                      </div>
                      <div className="min-w-0">
                        <p className="truncate text-sm font-medium text-text">
                          {patient ? `${patient.first_name} ${patient.last_name}` : "Patient"}
                          {patient?.patient_number ? ` · ${patient.patient_number}` : ""}
                        </p>
                        <p className="text-xs text-text-muted">
                          {row.type === "labo" ? "Laboratoire" : EXAM_TYPE_LABEL[(row.order as PrescriberImagingOrder).exam_type]}
                          {row.order.ordered_at ? ` · ${formatDate(row.order.ordered_at)}` : ""}
                          {row.order.site ? ` · ${row.order.site.name}` : ""}
                        </p>
                      </div>
                    </div>
                    <div className="flex shrink-0 items-center gap-3">
                      <Badge status={meta.status}>{meta.label}</Badge>
                      <ChevronDown
                        size={16}
                        className={`text-text-subtle transition-transform ${isOpen ? "rotate-180" : ""}`}
                      />
                    </div>
                  </button>

                  {isOpen && (
                    <div className="space-y-2 border-t border-border px-4 py-3">
                      {row.order.notes && <p className="text-xs text-text-muted">Motif : {row.order.notes}</p>}
                      {row.type === "labo" ? (
                        <ul className="space-y-1.5">
                          {(row.order as PrescriberLabOrder).items.map((item) => (
                            <li key={item.id} className="flex items-center justify-between gap-3 text-sm">
                              <span className="text-text">{item.label ?? item.code ?? "Analyse"}</span>
                              {row.tracking === "transmis" ? (
                                <span className="text-text-muted">
                                  {item.value ?? "—"} {item.unit ?? ""}
                                  {item.interpretation ? ` (${item.interpretation})` : ""}
                                </span>
                              ) : (
                                <span className="text-xs text-text-subtle">Résultat non transmis</span>
                              )}
                            </li>
                          ))}
                        </ul>
                      ) : (
                        <ul className="space-y-2">
                          {(row.order as PrescriberImagingOrder).studies.length === 0 && (
                            <li className="text-xs text-text-subtle">Aucun examen enregistré pour l'instant.</li>
                          )}
                          {(row.order as PrescriberImagingOrder).studies.map((study) => (
                            <li key={study.id} className="text-sm">
                              <p className="text-text-muted">
                                {study.modality ?? "Examen"}
                                {study.performed_at ? ` · ${formatDate(study.performed_at)}` : ""}
                              </p>
                              {row.tracking === "transmis" ? (
                                <p className="mt-1 whitespace-pre-wrap text-text">
                                  {study.report ?? "Compte-rendu non disponible."}
                                </p>
                              ) : (
                                <p className="mt-1 text-xs text-text-subtle">Compte-rendu non transmis</p>
                              )}
                            </li>
                          ))}
                        </ul>
                      )}
                    </div>
                  )}
                </div>
              );
            })}
          </CardContent>
        </Card>
      )}
    </div>
  );
}
