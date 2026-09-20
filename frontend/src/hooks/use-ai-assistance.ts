import { useMutation, useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";

/**
 * Étape 9 §4 / Étape 16 §3 — contrat non négociable de `App\Domain\Ai\Contracts\AiProvider` :
 * cet appel (`POST /consultations/{id}/ai-summary`) NE PERSISTE JAMAIS rien dans le dossier
 * patient malgré le verbe POST — c'est un GET conceptuel qui renvoie une proposition de texte.
 * `persisted` est toujours `false`. La seule façon d'inscrire ce texte au dossier est un appel
 * PATCH explicite et distinct sur `/consultations/{id}`, déclenché par un clic dédié du
 * praticien (voir ai-summary-card.tsx) — jamais automatiquement ici.
 */
export interface ConsultationSummaryResponse {
  summary: string;
  persisted: false;
  notice: string;
}

export function useGenerateConsultationSummary() {
  return useMutation({
    mutationFn: async (consultationId: number) => {
      const { data } = await api.post<ConsultationSummaryResponse>(
        `/consultations/${consultationId}/ai-summary`,
      );
      return data;
    },
  });
}

export interface VitalAnomaly {
  field: string;
  label: string;
  value: number;
  unit: string;
  reference_min: number;
  reference_max: number;
  message: string;
}

export interface LabAnomaly {
  lab_result_id: number;
  label: string | null;
  value: string | number;
  unit: string | null;
  reference_min: number | null;
  reference_max: number | null;
  interpretation: string | null;
  message: string;
}

export interface ConsultationAnomaliesResponse {
  consultation_id: number;
  method: string;
  anomalies: { vitals: VitalAnomaly[]; lab_results: LabAnomaly[] };
}

// Endpoint purement en lecture (`GET /consultations/{id}/anomalies`) — aucune écriture,
// aucun endpoint de "validation"/"acquittement" d'anomalie n'existe côté backend : ce hook ne
// fait qu'exposer la liste calculée, jamais une action de mutation.
export function useConsultationAnomalies(consultationId: number | undefined, enabled: boolean) {
  return useQuery({
    queryKey: ["consultations", consultationId, "anomalies"],
    queryFn: async () => {
      const { data } = await api.get<ConsultationAnomaliesResponse>(
        `/consultations/${consultationId}/anomalies`,
      );
      return data;
    },
    enabled: enabled && Boolean(consultationId),
  });
}
