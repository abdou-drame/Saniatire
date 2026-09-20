import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { CimCodeSearch } from "@/components/clinical/cim-code-search";
import { DiagnosisChip } from "@/components/clinical/diagnosis-chip";
import { apiErrorMessage } from "@/lib/api-error";
import { api } from "@/lib/api";
import type { ConsultationDiagnosis, DiagnosisType, IcdCode } from "@/types/api";

export function DiagnosisPicker({
  consultationId,
  diagnoses,
}: {
  consultationId: number;
  diagnoses: ConsultationDiagnosis[];
}) {
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const hasPrincipal = diagnoses.some((d) => d.type === "principal");

  const addMutation = useMutation({
    mutationFn: async ({ code, type }: { code: IcdCode; type: DiagnosisType }) =>
      api.post(`/consultations/${consultationId}/diagnoses`, {
        icd_code_id: code.id,
        type,
        status: "provisoire",
      }),
    onSuccess: () => {
      setError(null);
      // Prefix-only invalidation: this component is fed by several different
      // consultation queries depending on the screen (e.g. useOpenConsultation's
      // ["consultations", "open", patientId, userId] on the general consultation
      // form vs. SpecialtyDiagnosisCard's own ["consultations", consultationId]).
      // Invalidating just ["consultations", consultationId] silently missed the
      // former, so the POST succeeded but the UI never refreshed.
      queryClient.invalidateQueries({ queryKey: ["consultations"] });
    },
    onError: (err) => setError(apiErrorMessage(err)),
  });

  const principal = diagnoses.find((d) => d.type === "principal");
  const secondaires = diagnoses.filter((d) => d.type === "secondaire");

  return (
    <div className="space-y-4">
      <div className="space-y-2">
        <p className="text-xs font-medium text-text-muted">
          {hasPrincipal ? "Ajouter un diagnostic secondaire" : "Ajouter le diagnostic principal"}
        </p>
        <CimCodeSearch
          disabled={addMutation.isPending}
          excludeIds={diagnoses.map((d) => d.icd_code_id)}
          onSelect={(code) => addMutation.mutate({ code, type: hasPrincipal ? "secondaire" : "principal" })}
        />
        {!hasPrincipal && (
          <p className="text-[11px] text-text-subtle">
            Le premier code ajouté devient le diagnostic principal.
          </p>
        )}
        {error && <p className="text-xs text-danger">{error}</p>}
      </div>

      {principal && (
        <DiagnosisChip diagnosis={principal} consultationId={consultationId} />
      )}

      {secondaires.length > 0 && (
        <div className="space-y-2">
          {secondaires.map((d) => (
            <DiagnosisChip key={d.id} diagnosis={d} consultationId={consultationId} />
          ))}
        </div>
      )}

      {diagnoses.length === 0 && (
        <p className="text-xs text-text-subtle">Aucun diagnostic codifié pour cette consultation.</p>
      )}
    </div>
  );
}
