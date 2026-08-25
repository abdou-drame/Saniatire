import { useQuery } from "@tanstack/react-query";
import { ArrowLeft } from "lucide-react";
import type { ReactNode } from "react";
import { Link, useNavigate } from "react-router-dom";
import { Card } from "@/components/ui/card";
import { ErrorState } from "@/components/ui/error-state";
import { Skeleton } from "@/components/ui/loading-state";
import { api } from "@/lib/api";
import { apiErrorMessage } from "@/lib/api-error";
import { age } from "@/lib/datetime";
import { SPECIALTY_UI_REGISTRY } from "@/lib/specialty-registry";
import type { Patient } from "@/types/api";
import type { SpecialtyType } from "@/types/specialty";

/**
 * The generic "shell" every specialty consultation screen mounts into:
 * fetches the patient once, renders the back link / loading / error states
 * and a specialty-branded header, then hands the resolved patient to
 * `children` — this is what makes a 4th specialty screen not need to
 * re-implement any of this chrome.
 */
export function SpecialtyPageShell({
  patientId,
  specialty,
  children,
}: {
  patientId: number;
  specialty: SpecialtyType;
  children: (patient: Patient) => ReactNode;
}) {
  const navigate = useNavigate();
  const meta = SPECIALTY_UI_REGISTRY[specialty];
  const Icon = meta.icon;

  const patientQuery = useQuery({
    queryKey: ["patients", patientId],
    queryFn: async () => {
      const { data } = await api.get<{ data: Patient }>(`/patients/${patientId}`);
      return data.data;
    },
    enabled: Number.isFinite(patientId),
  });

  if (!Number.isFinite(patientId)) {
    return <ErrorState message="Identifiant patient invalide." />;
  }

  return (
    <div className="space-y-6">
      <button
        onClick={() => navigate(`/patients/${patientId}`)}
        className="flex items-center gap-1.5 text-xs text-text-muted hover:text-text"
      >
        <ArrowLeft size={14} />
        Retour au dossier patient
      </button>

      {patientQuery.isLoading && (
        <Card className="p-6">
          <Skeleton className="h-6 w-48" />
          <Skeleton className="mt-3 h-4 w-64" />
        </Card>
      )}

      {patientQuery.isError && (
        <ErrorState message={apiErrorMessage(patientQuery.error)} onRetry={() => patientQuery.refetch()} />
      )}

      {patientQuery.data && (
        <>
          <Card className="p-6">
            <div className="flex items-center gap-4">
              <div className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-full ${meta.colorClass}`}>
                <Icon size={20} />
              </div>
              <div>
                <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">{meta.label}</p>
                <h1 className="font-heading text-lg font-semibold text-text">
                  {patientQuery.data.first_name} {patientQuery.data.last_name}
                </h1>
                <p className="font-tabular text-xs text-text-subtle">
                  {patientQuery.data.patient_number} · {age(patientQuery.data.birth_date)} ans
                </p>
              </div>
              <Link to={`/patients/${patientId}`} className="ml-auto text-xs text-accent-light hover:underline">
                Voir le dossier complet
              </Link>
            </div>
          </Card>

          {children(patientQuery.data)}
        </>
      )}
    </div>
  );
}
