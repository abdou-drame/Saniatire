import { useQuery } from "@tanstack/react-query";
import { useAuth } from "@/hooks/use-auth";
import { api } from "@/lib/api";
import type { Consultation, Paginated } from "@/types/api";

/**
 * The patient's currently open ("en_cours") consultation for the logged-in
 * practitioner, if any — shared by every specialty screen's create-record
 * flow so a new specialty record can silently attach to it via
 * `consultation_id` (the backend's mechanism for surfacing the specialty in
 * the patient timeline and gating diagnosis capture), without duplicating
 * this lookup per specialty.
 */
export function useOpenConsultation(patientId: number) {
  const { user, hasRole } = useAuth();

  return useQuery({
    queryKey: ["consultations", "open", patientId, user?.id],
    queryFn: async () => {
      const { data } = await api.get<Paginated<Consultation>>("/consultations", {
        params: { patient_id: patientId, practitioner_id: user!.id, status: "en_cours" },
      });
      return data.data[0] ?? null;
    },
    enabled: Boolean(user) && hasRole("medecin") && Number.isFinite(patientId),
  });
}
