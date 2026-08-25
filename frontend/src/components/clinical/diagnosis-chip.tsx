import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Check, ChevronRight } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { useIcdAncestors } from "@/hooks/use-icd-ancestors";
import { api } from "@/lib/api";
import type { ConsultationDiagnosis, IcdCode } from "@/types/api";

export function DiagnosisChip({
  diagnosis,
  consultationId,
}: {
  diagnosis: ConsultationDiagnosis;
  consultationId: number;
}) {
  const queryClient = useQueryClient();
  const pseudoCode: IcdCode = {
    id: diagnosis.icd_code_id,
    code: diagnosis.code,
    version: diagnosis.version,
    label: diagnosis.label,
    parent_id: null,
    level: "code",
    status: "actif",
  };
  const ancestors = useIcdAncestors(pseudoCode);

  const confirmMutation = useMutation({
    mutationFn: async () =>
      api.patch(`/consultations/${consultationId}/diagnoses/${diagnosis.id}`, { status: "confirme" }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["consultations", consultationId] }),
  });

  return (
    <div className="rounded-md border border-border bg-bg px-3 py-2.5">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-1.5 text-[11px] text-text-subtle">
            {diagnosis.type === "principal" ? (
              <Badge status="accent2" dot={false}>
                Principal
              </Badge>
            ) : (
              <Badge status="neutral" dot={false}>
                Secondaire
              </Badge>
            )}
            <Badge status={diagnosis.status === "confirme" ? "success" : "warning"}>
              {diagnosis.status === "confirme" ? "Confirmé" : "Provisoire"}
            </Badge>
          </div>
          <p className="mt-1.5 truncate text-sm text-text">
            <span className="font-mono text-accent-light">{diagnosis.code}</span> — {diagnosis.label}
          </p>
          {ancestors.data && ancestors.data.length > 0 && (
            <p className="mt-1 flex flex-wrap items-center gap-1 text-[11px] text-text-subtle">
              {ancestors.data.map((a, i) => (
                <span key={a.id} className="flex items-center gap-1">
                  {i > 0 && <ChevronRight size={10} />}
                  {a.label}
                </span>
              ))}
            </p>
          )}
        </div>
        {diagnosis.status === "provisoire" && (
          <button
            type="button"
            onClick={() => confirmMutation.mutate()}
            disabled={confirmMutation.isPending}
            className="flex shrink-0 items-center gap-1 rounded-md border border-border-strong px-2 py-1 text-[11px] text-text-muted hover:bg-surface-hover disabled:opacity-50"
          >
            <Check size={12} />
            Confirmer
          </button>
        )}
      </div>
    </div>
  );
}
