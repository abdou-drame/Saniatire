import { ArrowLeft } from "lucide-react";
import { Link, useParams } from "react-router-dom";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { ErrorState } from "@/components/ui/error-state";
import { TableSkeleton } from "@/components/ui/loading-state";
import { usePortalComplaint } from "@/hooks/portal/use-portal-complaints";
import { formatDateTime } from "@/lib/datetime";
import { portalErrorMessage } from "@/lib/portal-error";
import { COMPLAINT_STATUT_BADGE, COMPLAINT_STATUT_LABEL } from "@/pages/qualite/qualite-status";

/**
 * `responses` ne contient ici que les réponses `visible_patient=true`
 * (filtrées côté serveur, PatientPortalController::complaint) — aucune note
 * interne ne peut jamais apparaître sur cette page.
 */
export function PortalComplaintDetailPage() {
  const { id } = useParams<{ id: string }>();
  const complaintQuery = usePortalComplaint(Number(id));

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Link to="/portail/reclamations" className="text-text-muted hover:text-text">
          <ArrowLeft size={18} />
        </Link>
        <h2 className="font-heading text-lg font-semibold text-text">Détail de la réclamation</h2>
      </div>

      {complaintQuery.isError ? (
        <ErrorState message={portalErrorMessage(complaintQuery.error)} onRetry={() => complaintQuery.refetch()} />
      ) : complaintQuery.isLoading ? (
        <TableSkeleton rows={5} columns={3} />
      ) : (
        <>
          <Card>
            <CardContent className="space-y-4 pt-5">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <p className="text-sm font-medium text-text">{complaintQuery.data!.motif}</p>
                  <p className="text-xs text-text-muted">Créée le {formatDateTime(complaintQuery.data!.created_at)}</p>
                </div>
                <Badge status={COMPLAINT_STATUT_BADGE[complaintQuery.data!.statut]}>
                  {COMPLAINT_STATUT_LABEL[complaintQuery.data!.statut]}
                </Badge>
              </div>

              <div className="border-t border-border pt-4">
                <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Description</p>
                <p className="mt-1 whitespace-pre-wrap text-sm text-text">{complaintQuery.data!.description}</p>
              </div>

              {complaintQuery.data!.service_concerne && (
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Prestation concernée</p>
                  <p className="mt-1 text-sm text-text">{complaintQuery.data!.service_concerne}</p>
                </div>
              )}

              {complaintQuery.data!.resolved_at && (
                <p className="text-xs text-text-muted">
                  Résolue le {formatDateTime(complaintQuery.data!.resolved_at)}
                </p>
              )}
              {complaintQuery.data!.closed_at && (
                <p className="text-xs text-text-muted">Clôturée le {formatDateTime(complaintQuery.data!.closed_at)}</p>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardContent className="space-y-3 pt-5">
              <h3 className="text-sm font-medium text-text">Réponses</h3>
              {(complaintQuery.data!.responses ?? []).length === 0 ? (
                <p className="text-sm text-text-muted">Aucune réponse pour le moment.</p>
              ) : (
                <div className="space-y-2">
                  {complaintQuery.data!.responses!.map((response) => (
                    <div key={response.id} className="rounded-md border border-border bg-surface px-4 py-3">
                      <p className="text-sm text-text">{response.message}</p>
                      <p className="mt-1 text-xs text-text-subtle">
                        {response.auteur_label ?? "Équipe"} · {formatDateTime(response.created_at)}
                      </p>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </>
      )}
    </div>
  );
}
