import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated } from "@/types/api";
import type { OccupationalHealthRecord } from "@/types/specialty";

export function occupationalHealthRecordsQueryKey(patientId: number) {
  return ["specialty", "medecine_travail", patientId] as const;
}

export function useOccupationalHealthRecords(patientId: number) {
  return useQuery({
    queryKey: occupationalHealthRecordsQueryKey(patientId),
    queryFn: async () => {
      const { data } = await api.get<Paginated<OccupationalHealthRecord>>("/occupational-health-records", {
        params: { patient_id: patientId },
      });
      return data.data;
    },
    enabled: Number.isFinite(patientId),
  });
}

export function useCreateOccupationalHealthRecord(patientId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: OccupationalHealthRecord }>("/occupational-health-records", {
        ...payload,
        patient_id: patientId,
      });
      return data.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: occupationalHealthRecordsQueryKey(patientId) }),
  });
}
