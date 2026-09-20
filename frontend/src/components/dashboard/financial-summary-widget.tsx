import { PiggyBank, Receipt, TrendingUp } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ErrorState } from "@/components/ui/error-state";
import { KpiCard } from "@/components/ui/kpi-card";
import { KpiRowSkeleton } from "@/components/ui/loading-state";
import { useFinancialDashboard } from "@/hooks/use-dashboards";
import { apiErrorMessage } from "@/lib/api-error";
import { defaultDashboardRange } from "@/lib/date-range";
import { formatFcfa, formatRatioAsPercent } from "@/lib/format";

/** Résumé sur 30 jours glissants — le détail complet vit sur /rapports. */
export function FinancialSummaryWidget() {
  const navigate = useNavigate();
  const { from, to } = defaultDashboardRange();
  const query = useFinancialDashboard({ from, to });

  return (
    <Card>
      <CardHeader>
        <CardTitle>Finances — 30 derniers jours</CardTitle>
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
            <KpiCard label="Encaissements" value={formatFcfa(query.data?.encaissements_total ?? 0)} icon={PiggyBank} />
            <KpiCard label="Taux de recouvrement" value={formatRatioAsPercent(query.data?.taux_recouvrement ?? null)} icon={TrendingUp} />
            <KpiCard
              label="Part patient facturée"
              value={formatFcfa(query.data?.repartition_assureur_patient.part_patient ?? 0)}
              icon={Receipt}
            />
          </div>
        )}
      </CardContent>
    </Card>
  );
}
