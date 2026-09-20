import { Activity, Bed, Stethoscope } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ErrorState } from "@/components/ui/error-state";
import { KpiCard } from "@/components/ui/kpi-card";
import { KpiRowSkeleton } from "@/components/ui/loading-state";
import { useMedicalDashboard } from "@/hooks/use-dashboards";
import { apiErrorMessage } from "@/lib/api-error";
import { defaultDashboardRange } from "@/lib/date-range";
import { formatNumber, formatPercent } from "@/lib/format";

/** Résumé sur 30 jours glissants — le détail complet vit sur /rapports. */
export function MedicalSummaryWidget() {
  const navigate = useNavigate();
  const { from, to } = defaultDashboardRange();
  const query = useMedicalDashboard({ from, to });

  const occupationMoyenne = query.data?.occupation_lits.length
    ? query.data.occupation_lits.reduce((sum, w) => sum + w.occupancy_rate, 0) / query.data.occupation_lits.length
    : null;

  return (
    <Card>
      <CardHeader>
        <CardTitle>Activité médicale — 30 derniers jours</CardTitle>
        <Button variant="ghost" size="sm" onClick={() => navigate("/rapports")}>
          Rapport complet
        </Button>
      </CardHeader>
      <CardContent>
        {query.isError ? (
          <ErrorState message={apiErrorMessage(query.error)} onRetry={() => query.refetch()} />
        ) : query.isLoading ? (
          <KpiRowSkeleton count={3} />
        ) : (
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <KpiCard label="Consultations" value={formatNumber(query.data?.consultations.total ?? 0)} icon={Stethoscope} />
            <KpiCard
              label="Attente moyenne"
              value={
                query.data?.temps_attente.average_wait_minutes != null
                  ? `${Math.round(query.data.temps_attente.average_wait_minutes)} min`
                  : "—"
              }
              icon={Activity}
            />
            <KpiCard label="Occupation lits moyenne" value={formatPercent(occupationMoyenne)} icon={Bed} />
          </div>
        )}
      </CardContent>
    </Card>
  );
}
