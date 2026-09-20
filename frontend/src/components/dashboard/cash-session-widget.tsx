import { PiggyBank } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Skeleton } from "@/components/ui/loading-state";
import { useCurrentCashSession } from "@/hooks/use-cash-sessions";
import { apiErrorMessage } from "@/lib/api-error";
import { formatFcfa } from "@/lib/format";
import { formatDateTime } from "@/lib/datetime";

export function CashSessionWidget() {
  const navigate = useNavigate();
  const query = useCurrentCashSession();

  return (
    <Card>
      <CardHeader>
        <CardTitle>Session de caisse</CardTitle>
        <Button variant="ghost" size="sm" onClick={() => navigate("/caisse")}>
          Ouvrir la caisse
        </Button>
      </CardHeader>
      <CardContent>
        {query.isError ? (
          <ErrorState message={apiErrorMessage(query.error)} onRetry={() => query.refetch()} />
        ) : query.isLoading ? (
          <Skeleton className="h-16 w-full" />
        ) : query.data ? (
          <div className="space-y-1">
            <p className="text-2xl font-semibold text-text">{formatFcfa(query.data.montant_ouverture)}</p>
            <p className="text-sm text-text-muted">Ouverte depuis le {formatDateTime(query.data.ouverte_le)}</p>
          </div>
        ) : (
          <EmptyState icon={PiggyBank} title="Aucune session ouverte" description="Ouvrez une session pour commencer à encaisser." />
        )}
      </CardContent>
    </Card>
  );
}
