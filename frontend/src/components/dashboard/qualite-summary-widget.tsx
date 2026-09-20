import { Clock, MessageSquareWarning, Star } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ErrorState } from "@/components/ui/error-state";
import { KpiCard } from "@/components/ui/kpi-card";
import { KpiRowSkeleton } from "@/components/ui/loading-state";
import { useQualiteDashboard } from "@/hooks/use-dashboards";
import { apiErrorMessage } from "@/lib/api-error";
import { defaultDashboardRange } from "@/lib/date-range";
import { formatNumber } from "@/lib/format";

/** Résumé sur 30 jours glissants — le détail complet vit sur /qualite. */
export function QualiteSummaryWidget() {
  const navigate = useNavigate();
  const { from, to } = defaultDashboardRange();
  const query = useQualiteDashboard({ from, to });

  const reclamationsOuvertes = query.data?.reclamations.par_statut.find((r) => r.statut === "ouverte")?.total ?? 0;

  return (
    <Card>
      <CardHeader>
        <CardTitle>Qualité — 30 derniers jours</CardTitle>
        <Button variant="ghost" size="sm" onClick={() => navigate("/qualite")}>
          Détail
        </Button>
      </CardHeader>
      <CardContent>
        {query.isError ? (
          <ErrorState message={apiErrorMessage(query.error)} onRetry={() => query.refetch()} />
        ) : query.isLoading ? (
          <KpiRowSkeleton count={3} />
        ) : (
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <KpiCard
              label="Satisfaction moyenne"
              value={query.data?.score_moyen_satisfaction != null ? query.data.score_moyen_satisfaction.toFixed(1) : "—"}
              icon={Star}
            />
            <KpiCard label="Réclamations ouvertes" value={formatNumber(reclamationsOuvertes)} icon={MessageSquareWarning} />
            <KpiCard
              label="Délai moyen de résolution"
              value={
                query.data?.delai_moyen_resolution_heures != null
                  ? `${Math.round(query.data.delai_moyen_resolution_heures)} h`
                  : "—"
              }
              icon={Clock}
            />
          </div>
        )}
      </CardContent>
    </Card>
  );
}
