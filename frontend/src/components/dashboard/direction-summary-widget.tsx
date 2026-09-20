import { Building2, PiggyBank, Users } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ErrorState } from "@/components/ui/error-state";
import { KpiCard } from "@/components/ui/kpi-card";
import { KpiRowSkeleton } from "@/components/ui/loading-state";
import { useDirectionDashboard } from "@/hooks/use-dashboards";
import { apiErrorMessage } from "@/lib/api-error";
import { defaultDashboardRange } from "@/lib/date-range";
import { formatFcfa, formatNumber, formatPercent } from "@/lib/format";

/** Résumé sur 30 jours glissants — le détail complet, filtrable et exportable, vit sur /rapports. */
export function DirectionSummaryWidget() {
  const navigate = useNavigate();
  const { from, to } = defaultDashboardRange();
  const query = useDirectionDashboard({ from, to });

  return (
    <Card>
      <CardHeader>
        <CardTitle>Direction — 30 derniers jours</CardTitle>
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
            <KpiCard label="Chiffre d'affaires encaissé" value={formatFcfa(query.data?.consolide.ca_total ?? 0)} icon={PiggyBank} />
            <KpiCard label="Patients (période)" value={formatNumber(query.data?.consolide.nombre_patients ?? 0)} icon={Users} />
            <KpiCard label="Taux d'occupation moyen" value={formatPercent(query.data?.consolide.taux_occupation_moyen ?? null)} icon={Building2} />
          </div>
        )}
      </CardContent>
    </Card>
  );
}
