import { useQuery } from "@tanstack/react-query";
import { DiagnosisPicker } from "@/components/clinical/diagnosis-picker";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/loading-state";
import { api } from "@/lib/api";
import type { Consultation } from "@/types/api";

/**
 * Same diagnosis-capture UI as the general consultation form
 * (`CimCodeSearch` via `DiagnosisPicker`), reused unchanged here: a
 * specialty record never carries its own diagnosis field — it always
 * routes through the parent `Consultation` it's linked to.
 */
export function SpecialtyDiagnosisCard({ consultationId }: { consultationId: number | null }) {
  const consultationQuery = useQuery({
    queryKey: ["consultations", consultationId],
    queryFn: async () => {
      const { data } = await api.get<{ data: Consultation }>(`/consultations/${consultationId}`);
      return data.data;
    },
    enabled: Boolean(consultationId),
  });

  return (
    <Card>
      <CardHeader>
        <CardTitle>Diagnostic (CIM)</CardTitle>
      </CardHeader>
      <CardContent>
        {!consultationId && (
          <p className="text-xs text-text-subtle">
            Aucune consultation générale ouverte n'est associée — démarrez une consultation pour ce patient afin de
            pouvoir y coder un diagnostic.
          </p>
        )}
        {consultationId && consultationQuery.isLoading && <Skeleton className="h-9 w-full" />}
        {consultationId && consultationQuery.data && (
          <DiagnosisPicker consultationId={consultationId} diagnoses={consultationQuery.data.diagnoses} />
        )}
      </CardContent>
    </Card>
  );
}
