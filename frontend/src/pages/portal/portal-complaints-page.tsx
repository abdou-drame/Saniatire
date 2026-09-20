import { MessageSquareWarning, Plus } from "lucide-react";
import { Link, useNavigate } from "react-router-dom";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { TableSkeleton } from "@/components/ui/loading-state";
import { usePortalComplaints } from "@/hooks/portal/use-portal-complaints";
import { formatDate } from "@/lib/datetime";
import { portalErrorMessage } from "@/lib/portal-error";
import { COMPLAINT_STATUT_BADGE, COMPLAINT_STATUT_LABEL } from "@/pages/qualite/qualite-status";

export function PortalComplaintsPage() {
  const navigate = useNavigate();
  const complaintsQuery = usePortalComplaints();

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-3">
        <h2 className="font-heading text-lg font-semibold text-text">Mes réclamations</h2>
        <Link to="/portail/reclamations/nouveau">
          <Button size="sm">
            <Plus size={14} />
            Nouvelle réclamation
          </Button>
        </Link>
      </div>

      {complaintsQuery.isError ? (
        <ErrorState message={portalErrorMessage(complaintsQuery.error)} onRetry={() => complaintsQuery.refetch()} />
      ) : complaintsQuery.isLoading ? (
        <TableSkeleton rows={4} columns={3} />
      ) : complaintsQuery.data!.length === 0 ? (
        <EmptyState
          icon={MessageSquareWarning}
          title="Aucune réclamation"
          description="Vous n'avez soumis aucune réclamation pour le moment."
          actionLabel="Nouvelle réclamation"
          onAction={() => navigate("/portail/reclamations/nouveau")}
        />
      ) : (
        <Card>
          <CardContent className="space-y-2 pt-5">
            {complaintsQuery.data!.map((complaint) => (
              <Link
                key={complaint.id}
                to={`/portail/reclamations/${complaint.id}`}
                className="flex items-center justify-between gap-4 rounded-md border border-border bg-surface px-4 py-3 transition-colors hover:bg-surface-hover"
              >
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium text-text">{complaint.motif}</p>
                  <p className="text-xs text-text-muted">{formatDate(complaint.created_at)}</p>
                </div>
                <Badge status={COMPLAINT_STATUT_BADGE[complaint.statut]}>
                  {COMPLAINT_STATUT_LABEL[complaint.statut]}
                </Badge>
              </Link>
            ))}
          </CardContent>
        </Card>
      )}
    </div>
  );
}
