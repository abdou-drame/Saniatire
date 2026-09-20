import { AlertTriangle, CheckCircle2 } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/loading-state";
import { useConsultationAnomalies } from "@/hooks/use-ai-assistance";
import { apiErrorMessage } from "@/lib/api-error";

export interface AnomalyAlertCardProps {
  consultationId: number;
}

// Purement informatif : GET /consultations/{id}/anomalies ne modifie jamais rien et il
// n'existe aucun endpoint de "validation"/"acquittement" d'anomalie côté backend — ce
// composant ne déclenche donc aucune mutation, seulement l'affichage de la liste calculée.
export function AnomalyAlertCard({ consultationId }: AnomalyAlertCardProps) {
  const anomaliesQuery = useConsultationAnomalies(consultationId, true);

  const vitals = anomaliesQuery.data?.anomalies.vitals ?? [];
  const labResults = anomaliesQuery.data?.anomalies.lab_results ?? [];
  const hasAnomalies = vitals.length > 0 || labResults.length > 0;

  return (
    <Card>
      <CardHeader>
        <CardTitle>Anomalies détectées</CardTitle>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={() => anomaliesQuery.refetch()}
          disabled={anomaliesQuery.isFetching}
        >
          Rafraîchir
        </Button>
      </CardHeader>
      <CardContent className="space-y-3">
        <p className="text-xs text-text-subtle">
          Liste informative — aucune modification automatique de la donnée clinique.
        </p>

        {anomaliesQuery.isLoading && (
          <div className="space-y-2">
            <Skeleton className="h-4 w-full" />
            <Skeleton className="h-4 w-3/4" />
          </div>
        )}

        {anomaliesQuery.isError && (
          <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
            {apiErrorMessage(anomaliesQuery.error)}
          </p>
        )}

        {anomaliesQuery.isSuccess && !hasAnomalies && (
          <div className="flex items-center gap-2 text-xs text-success">
            <CheckCircle2 size={14} />
            Aucune anomalie détectée sur les constantes ou résultats de laboratoire de cette
            consultation.
          </div>
        )}

        {anomaliesQuery.isSuccess && hasAnomalies && (
          <ul className="space-y-2">
            {vitals.map((a) => (
              <li
                key={`vital-${a.field}`}
                className="flex items-start gap-2 rounded-md border border-warning/30 bg-warning/10 px-3 py-2"
              >
                <AlertTriangle size={14} className="mt-0.5 shrink-0 text-warning" />
                <div className="flex-1 space-y-1">
                  <div className="flex items-center gap-2">
                    <span className="text-xs font-medium text-text">{a.label}</span>
                    <Badge status="warning">
                      {a.value} {a.unit}
                    </Badge>
                  </div>
                  <p className="text-xs text-text-muted">{a.message}</p>
                </div>
              </li>
            ))}
            {labResults.map((a) => (
              <li
                key={`lab-${a.lab_result_id}`}
                className={
                  a.interpretation === "critique"
                    ? "flex items-start gap-2 rounded-md border border-danger/30 bg-danger/10 px-3 py-2"
                    : "flex items-start gap-2 rounded-md border border-warning/30 bg-warning/10 px-3 py-2"
                }
              >
                <AlertTriangle
                  size={14}
                  className={
                    a.interpretation === "critique"
                      ? "mt-0.5 shrink-0 text-danger"
                      : "mt-0.5 shrink-0 text-warning"
                  }
                />
                <div className="flex-1 space-y-1">
                  <div className="flex items-center gap-2">
                    <span className="text-xs font-medium text-text">{a.label ?? "Résultat de laboratoire"}</span>
                    <Badge status={a.interpretation === "critique" ? "danger" : "warning"}>
                      {a.value} {a.unit ?? ""}
                    </Badge>
                  </div>
                  <p className="text-xs text-text-muted">{a.message}</p>
                </div>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}
