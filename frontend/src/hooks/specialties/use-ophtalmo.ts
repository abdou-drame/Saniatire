import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated } from "@/types/api";
import type { OphtalmoRecord } from "@/types/specialty";

export function ophtalmoRecordsQueryKey(patientId: number) {
  return ["specialty", "ophtalmo", patientId] as const;
}

export function useOphtalmoRecords(patientId: number) {
  return useQuery({
    queryKey: ophtalmoRecordsQueryKey(patientId),
    queryFn: async () => {
      const { data } = await api.get<Paginated<OphtalmoRecord>>("/ophtalmo-records", {
        params: { patient_id: patientId },
      });
      return data.data;
    },
    enabled: Number.isFinite(patientId),
  });
}

export function useCreateOphtalmoRecord(patientId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: OphtalmoRecord }>("/ophtalmo-records", {
        ...payload,
        patient_id: patientId,
      });
      return data.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ophtalmoRecordsQueryKey(patientId) }),
  });
}
