import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Consultation, ConsultationStatus, Paginated } from "@/types/api";

export function useConsultations(filters: { status?: ConsultationStatus; patientId?: number; practitionerId?: number } = {}) {
  return useQuery({
    queryKey: ["consultations", "list", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<Consultation>>("/consultations", {
        params: { status: filters.status, patient_id: filters.patientId, practitioner_id: filters.practitionerId },
      });
      return data;
    },
  });
}
